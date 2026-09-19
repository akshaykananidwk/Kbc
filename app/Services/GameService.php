<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Repositories\GameRepository;
use App\Repositories\GiftRepository;
use App\Repositories\LifelineRepository;
use App\Repositories\PrizeLevelRepository;
use App\Repositories\QuestionRepository;
use App\Services\AudiencePollService;
use RuntimeException;

/**
 * Server authoritative game engine.
 *
 * Both the operator screen and the display screen read their truth from
 * here - no game decision is ever taken in browser JavaScript.
 */
final class GameService
{
    public const STATE_NOT_STARTED   = 'GAME_NOT_STARTED';
    public const STATE_INTRO         = 'PARTICIPANT_INTRO';
    public const STATE_QUESTION      = 'QUESTION_DISPLAYED';
    public const STATE_TIMER_RUNNING = 'TIMER_RUNNING';
    public const STATE_TIMER_PAUSED  = 'TIMER_PAUSED';
    public const STATE_SELECTED      = 'ANSWER_SELECTED';
    public const STATE_LOCKED        = 'ANSWER_LOCKED';
    public const STATE_REVEALED      = 'RESULT_REVEALED';
    public const STATE_CORRECT       = 'CORRECT';
    public const STATE_WRONG         = 'WRONG';
    public const STATE_TIME_UP       = 'TIME_UP';
    public const STATE_COMPLETED     = 'GAME_COMPLETED';

    private Database $db;
    private GameRepository $games;
    private QuestionRepository $questions;
    private PrizeLevelRepository $levels;
    private GiftRepository $gifts;
    private LifelineRepository $lifelines;

    public function __construct(?Database $db = null)
    {
        $this->db        = $db ?? Database::instance();
        $this->games     = new GameRepository($this->db);
        $this->questions = new QuestionRepository($this->db);
        $this->levels    = new PrizeLevelRepository($this->db);
        $this->gifts     = new GiftRepository($this->db);
        $this->lifelines = new LifelineRepository($this->db);
    }

    public static function make(?Database $db = null): self
    {
        return new self($db);
    }

    // -----------------------------------------------------------------
    // Game lifecycle
    // -----------------------------------------------------------------

    public function createGame(
        int $participantId,
        ?int $operatorId,
        ?string $questionOrder = null,
        ?bool $isRehearsal = null,
        bool $replaceOpenGame = false,
        ?bool $allowRepeat = null
    ): array {
        $participant = $this->db->selectOne('SELECT * FROM participants WHERE id = ? LIMIT 1', [$participantId]);
        if ($participant === null) {
            throw new HttpException(422, 'Select a valid participant before starting a game.');
        }
        if ($this->levels->maxLevel() < 1) {
            throw new HttpException(422, 'Set up at least one prize level before starting a game.');
        }
        if ($this->questions->activeCount() < 1) {
            throw new HttpException(422, 'Add at least one active question before starting a game.');
        }

        // A show that was closed without ending the game properly used to
        // block every later game until somebody deleted it by hand. The
        // operator can now take over in one click instead.
        $running = $this->games->activeGame();
        if ($running !== null) {
            if (!$replaceOpenGame) {
                throw new HttpException(
                    409,
                    'Game ' . $running['game_code'] . ' is still open for '
                    . ($running['participant_name'] ?? 'a participant')
                    . '. End it and start the new game?',
                    ['open_game' => [
                        'id'          => (int) $running['id'],
                        'game_code'   => (string) $running['game_code'],
                        'participant' => (string) ($running['participant_name'] ?? ''),
                        'status'      => (string) $running['status'],
                    ]]
                );
            }

            $this->endGame((int) $running['id'], 'abandoned');
            AuditService::log(
                'game.replaced',
                'Game ' . $running['game_code'] . ' was ended automatically to start a new game.',
                'game',
                (int) $running['id']
            );
        }

        $order = $questionOrder ?? SettingsService::string('question_order', 'fixed');
        if (!in_array($order, ['balanced', 'fixed', 'random', 'category', 'difficulty'], true)) {
            $order = 'fixed';
        }

        $rehearsal = $isRehearsal ?? SettingsService::bool('rehearsal_mode', false);

        $gameId = $this->games->create([
            'game_code'       => $this->games->generateCode(),
            'participant_id'  => $participantId,
            'operator_id'     => $operatorId,
            'status'          => 'pending',
            'state'           => self::STATE_INTRO,
            'state_version'   => 1,
            'current_level'   => 0,
            'question_order'  => $order,
            'allow_repeat'    => $allowRepeat === null ? null : ($allowRepeat ? 1 : 0),
            'is_rehearsal'    => $rehearsal ? 1 : 0,
        ]);

        $this->games->logEvent($gameId, 'game.created', self::STATE_INTRO, 0, [
            'participant' => $participant['name'],
            'rehearsal'   => $rehearsal,
        ], $operatorId);
        AuditService::log(
            'game.created',
            ($rehearsal ? 'Started a REHEARSAL game for ' : 'Started a new game for ') . $participant['name'],
            'game',
            $gameId
        );

        return $this->state($gameId, true);
    }

    /**
     * Which category this level should draw from in a balanced game.
     *
     * The categories are dealt out in a cycle, so a ten-level ladder over
     * five categories asks two questions from each. The order of the cycle
     * is shuffled per game - deterministically, so re-serving the same level
     * always lands on the same category - which keeps every show different
     * without making the running order unpredictable mid-game.
     */
    private function categoryForLevel(int $gameId, int $levelNo): ?int
    {
        $categories = $this->db->select(
            "SELECT DISTINCT c.id
             FROM question_categories c
             INNER JOIN questions q ON q.category_id = c.id AND q.status = 'active'
             WHERE c.status = 'active' AND c.in_rotation = 1
             ORDER BY c.sort_order ASC, c.id ASC"
        );
        if ($categories === []) {
            return null;
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $categories);

        // A stable shuffle: order the categories by a hash of the game and the
        // category, so the sequence differs per game but never changes within
        // one. (shuffle() would give a different answer on every call.)
        usort($ids, static function (int $a, int $b) use ($gameId): int {
            $ha = md5($gameId . ':' . $a);
            $hb = md5($gameId . ':' . $b);
            return $ha <=> $hb;
        });

        return $ids[($levelNo - 1) % count($ids)];
    }

    /**
     * Which age group a participant plays in.
     *
     * The show runs in two halves - juniors and seniors - so the questions
     * follow the contestant. A participant can be pinned to a group by hand;
     * otherwise their age decides, and with no age recorded the questions
     * stay open to both.
     */
    public function ageGroupFor(int $participantId): string
    {
        if ($participantId < 1) {
            return '';
        }

        $participant = $this->db->selectOne(
            'SELECT age, age_group FROM participants WHERE id = ? LIMIT 1',
            [$participantId]
        );
        if ($participant === null) {
            return '';
        }

        $chosen = (string) ($participant['age_group'] ?? 'auto');
        if ($chosen === 'junior' || $chosen === 'senior') {
            return $chosen;
        }

        $age = (int) ($participant['age'] ?? 0);
        if ($age <= 0) {
            return '';
        }

        return $age <= SettingsService::int('junior_max_age', 20) ? 'junior' : 'senior';
    }

    /** Moves from the participant introduction to the first question. */
    public function startGame(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        if (in_array((string) $game['status'], ['completed', 'wrong_answer', 'time_up', 'quit', 'abandoned'], true)) {
            throw new HttpException(409, 'This game has already finished.');
        }

        $this->db->transaction(function () use ($game): void {
            $this->games->updateById((int) $game['id'], [
                'status'     => 'running',
                'started_at' => $game['started_at'] ?? date('Y-m-d H:i:s'),
            ]);
        });

        $this->loadLevel($gameId, 1);
        $this->games->logEvent($gameId, 'game.started', self::STATE_QUESTION, 1);

        return $this->state($gameId, true);
    }

    /**
     * Pick and serve the question for a level. A level that has already been
     * served keeps the same question so restarting is deterministic.
     */
    public function loadLevel(int $gameId, int $levelNo): array
    {
        $game = $this->mustFind($gameId);
        $level = $this->levels->findByLevel($levelNo);
        if ($level === null) {
            throw new HttpException(422, 'Prize level ' . $levelNo . ' is not configured.');
        }

        $existing = $this->db->selectOne(
            'SELECT * FROM game_questions WHERE game_id = ? AND level_no = ? LIMIT 1',
            [$gameId, $levelNo]
        );

        if ($existing !== null) {
            $questionId = (int) $existing['question_id'];
            $timeLimit  = (int) $existing['time_limit'];
        } else {
            // A rehearsal may reuse questions freely - it is practice, and it
            // must not burn through the question bank before the real show.
            // Order of precedence: the choice made for this game, then
            // rehearsal (practice must never burn the question bank), then
            // the global setting.
            $allowReuse = $game['allow_repeat'] !== null
                ? (int) $game['allow_repeat'] === 1
                : ((int) ($game['is_rehearsal'] ?? 0) === 1 || SettingsService::bool('repeat_questions', false));

            $order = (string) $game['question_order'];
            $categoryId = $level['category_id'] === null ? null : (int) $level['category_id'];
            $ageGroup = $this->ageGroupFor((int) $game['participant_id']);

            // Balanced order spreads the show evenly over the categories:
            // with five categories and a ten-level ladder that is two
            // questions from each, in a different order every game.
            if ($order === 'balanced' && $categoryId === null) {
                $categoryId = $this->categoryForLevel($gameId, $levelNo);
            }

            $question = $this->questions->pickForLevel(
                $levelNo,
                $order,
                $categoryId,
                (string) $level['difficulty'],
                $this->games->servedQuestionIds($gameId),
                $allowReuse,
                $ageGroup
            );

            // A category that has run dry must never stop the show.
            if ($question === null && $order === 'balanced') {
                $question = $this->questions->pickForLevel(
                    $levelNo,
                    'random',
                    $level['category_id'] === null ? null : (int) $level['category_id'],
                    (string) $level['difficulty'],
                    $this->games->servedQuestionIds($gameId),
                    $allowReuse,
                    $ageGroup
                );
            }

            // Nor may an age group with no questions of its own: a bank aimed
            // at seniors should still let a junior play rather than leaving
            // the operator with a game that cannot start.
            if ($question === null && $ageGroup !== '') {
                $question = $this->questions->pickForLevel(
                    $levelNo,
                    $order === 'balanced' ? 'random' : $order,
                    $level['category_id'] === null ? null : (int) $level['category_id'],
                    (string) $level['difficulty'],
                    $this->games->servedQuestionIds($gameId),
                    $allowReuse,
                    ''
                );

                if ($question !== null) {
                    $this->games->logEvent($gameId, 'question.age_group_fallback', null, $levelNo, [
                        'age_group' => $ageGroup,
                    ]);
                }
            }

            if ($question === null) {
                // Say which rule ran out, so the operator knows what to do
                // rather than hunting through settings mid-show.
                if (SettingsService::bool('no_repeat_today', true)
                    && $this->questions->availableToday($ageGroup) === 0) {
                    throw new HttpException(
                        422,
                        'આજે બધા પ્રશ્નો પુછાઈ ગયા છે. Every question has been asked today. '
                        . 'Turn off "no repeats today" on the setup screen, or add more questions.'
                    );
                }

                throw new HttpException(
                    422,
                    'No unused question is available for level ' . $levelNo
                    . '. Add more questions, or allow questions to be reused for this game.'
                );
            }

            $questionId = (int) $question['id'];
            $timeLimit  = (int) ($question['time_limit'] ?: $level['time_limit'] ?: SettingsService::int('default_time_limit', 30));

            $this->db->insert('game_questions', [
                'game_id'      => $gameId,
                'question_id'  => $questionId,
                'level_no'     => $levelNo,
                'prize_amount' => (float) $level['amount'],
                'gift_id'      => $level['gift_id'] === null ? null : (int) $level['gift_id'],
                'time_limit'   => $timeLimit,
                'served_at'    => date('Y-m-d H:i:s'),
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            $this->questions->markServed($questionId);
        }

        $this->games->updateById($gameId, [
            'current_level'       => $levelNo,
            'current_question_id' => $questionId,
            'selected_option'     => null,
            'is_locked'           => 0,
            'state'               => self::STATE_QUESTION,
            'timer_running'       => 0,
            'timer_started_at'    => null,
            'timer_total_ms'      => $timeLimit * 1000,
            'timer_remaining_ms'  => $timeLimit * 1000,
            'status'              => 'running',
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'question.displayed', self::STATE_QUESTION, $levelNo, ['question_id' => $questionId]);

        return $this->state($gameId, true);
    }

    // -----------------------------------------------------------------
    // Timer
    // -----------------------------------------------------------------

    public function startTimer(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $this->assertQuestionActive($game);

        if ((int) $game['is_locked'] === 1) {
            throw new HttpException(409, 'The answer is already locked.');
        }

        $remaining = (int) $game['timer_remaining_ms'];
        if ($remaining <= 0) {
            $remaining = (int) $game['timer_total_ms'];
        }

        $this->games->updateById($gameId, [
            'timer_running'      => 1,
            'timer_started_at'   => $this->now(),
            'timer_remaining_ms' => $remaining,
            'state'              => $game['selected_option'] !== null ? self::STATE_SELECTED : self::STATE_TIMER_RUNNING,
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'timer.started', self::STATE_TIMER_RUNNING, (int) $game['current_level']);

        return $this->state($gameId, true);
    }

    public function pauseTimer(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        if ((int) $game['timer_running'] !== 1) {
            return $this->state($gameId, true);
        }

        $remaining = $this->remainingMs($game);
        $this->games->updateById($gameId, [
            'timer_running'      => 0,
            'timer_started_at'   => null,
            'timer_remaining_ms' => $remaining,
            'state'              => self::STATE_TIMER_PAUSED,
            'status'             => 'paused',
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'timer.paused', self::STATE_TIMER_PAUSED, (int) $game['current_level'], [
            'remaining_ms' => $remaining,
        ]);

        return $this->state($gameId, true);
    }

    public function resumeTimer(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $this->assertQuestionActive($game);

        if ((int) $game['is_locked'] === 1) {
            throw new HttpException(409, 'The answer is locked - the timer cannot be resumed.');
        }
        if ((int) $game['timer_remaining_ms'] <= 0) {
            throw new HttpException(409, 'Time is already up. Restart the question to give more time.');
        }

        $this->games->updateById($gameId, [
            'timer_running'    => 1,
            'timer_started_at' => $this->now(),
            'state'            => $game['selected_option'] !== null ? self::STATE_SELECTED : self::STATE_TIMER_RUNNING,
            'status'           => 'running',
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'timer.resumed', self::STATE_TIMER_RUNNING, (int) $game['current_level']);

        return $this->state($gameId, true);
    }

    public function resetTimer(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $this->games->updateById($gameId, [
            'timer_running'      => 0,
            'timer_started_at'   => null,
            'timer_remaining_ms' => (int) $game['timer_total_ms'],
            'state'              => $game['selected_option'] !== null ? self::STATE_SELECTED : self::STATE_QUESTION,
            'status'             => 'running',
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'timer.reset', self::STATE_QUESTION, (int) $game['current_level']);

        return $this->state($gameId, true);
    }

    /** Re-runs the current question from the beginning, clearing any selection. */
    public function restartQuestion(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $level = (int) $game['current_level'];
        if ($level < 1) {
            throw new HttpException(409, 'No question is currently on air.');
        }

        $this->games->updateById($gameId, [
            'selected_option'    => null,
            'is_locked'          => 0,
            'timer_running'      => 0,
            'timer_started_at'   => null,
            'timer_remaining_ms' => (int) $game['timer_total_ms'],
            'state'              => self::STATE_QUESTION,
            'status'             => 'running',
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'question.restarted', self::STATE_QUESTION, $level);
        AuditService::log('game.question_restarted', 'Restarted question at level ' . $level, 'game', $gameId);

        return $this->state($gameId, true);
    }

    // -----------------------------------------------------------------
    // Answering
    // -----------------------------------------------------------------

    public function selectOption(int $gameId, string $option): array
    {
        $option = strtoupper(trim($option));
        if (!in_array($option, ['A', 'B', 'C', 'D'], true)) {
            throw new HttpException(422, 'Choose option A, B, C or D.');
        }

        $game = $this->mustFind($gameId);
        $this->assertQuestionActive($game);

        if ((int) $game['is_locked'] === 1) {
            throw new HttpException(409, 'The answer is locked. Use the override control to change it.');
        }
        if ($game['selected_option'] !== null && !SettingsService::bool('allow_answer_change', true)) {
            throw new HttpException(409, 'Changing the answer is disabled in settings.');
        }

        // A removed 50:50 option can no longer be chosen.
        $removed = $this->removedOptions($gameId, (int) $game['current_question_id']);
        if (in_array($option, $removed, true)) {
            throw new HttpException(409, 'Option ' . $option . ' was removed by the 50:50 lifeline.');
        }

        $this->games->updateById($gameId, [
            'selected_option' => $option,
            'state'           => self::STATE_SELECTED,
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'answer.selected', self::STATE_SELECTED, (int) $game['current_level'], [
            'option' => $option,
        ]);

        return $this->state($gameId, true);
    }

    public function lockAnswer(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $this->assertQuestionActive($game);

        if ($game['selected_option'] === null) {
            throw new HttpException(422, 'Select an option before locking the answer.');
        }
        if ((int) $game['is_locked'] === 1) {
            throw new HttpException(409, 'The answer is already locked.');
        }

        $remaining = $this->remainingMs($game);

        $this->games->updateById($gameId, [
            'is_locked'          => 1,
            'timer_running'      => 0,
            'timer_started_at'   => null,
            'timer_remaining_ms' => $remaining,
            'state'              => self::STATE_LOCKED,
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'answer.locked', self::STATE_LOCKED, (int) $game['current_level'], [
            'option'       => $game['selected_option'],
            'remaining_ms' => $remaining,
        ]);
        AuditService::log('game.answer_locked', 'Locked answer ' . $game['selected_option'] . ' at level ' . $game['current_level'], 'game', $gameId);

        if (SettingsService::bool('auto_reveal', false)) {
            return $this->revealResult($gameId);
        }

        return $this->state($gameId, true);
    }

    /** Explicit operator override - always audited. */
    public function unlockAnswer(int $gameId, string $reason = ''): array
    {
        $game = $this->mustFind($gameId);
        if ((int) $game['is_locked'] !== 1) {
            throw new HttpException(409, 'The answer is not locked.');
        }
        if (in_array((string) $game['state'], [self::STATE_CORRECT, self::STATE_WRONG, self::STATE_REVEALED], true)) {
            throw new HttpException(409, 'The result has already been revealed and cannot be unlocked.');
        }

        $this->games->updateById($gameId, [
            'is_locked' => 0,
            'state'     => self::STATE_SELECTED,
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'answer.override', self::STATE_SELECTED, (int) $game['current_level'], [
            'reason' => $reason,
        ]);
        AuditService::log(
            'game.answer_override',
            'Unlocked a locked answer at level ' . $game['current_level'] . ($reason === '' ? '' : ' - ' . $reason),
            'game',
            $gameId
        );

        return $this->state($gameId, true);
    }

    /** Called automatically by state() and by the operator's Time Up control. */
    public function markTimeUp(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        if ((int) $game['is_locked'] === 1) {
            return $this->state($gameId, true);
        }
        if ((string) $game['state'] === self::STATE_TIME_UP) {
            return $this->state($gameId, true);
        }

        $this->games->updateById($gameId, [
            'timer_running'      => 0,
            'timer_started_at'   => null,
            'timer_remaining_ms' => 0,
            'state'              => self::STATE_TIME_UP,
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'timer.expired', self::STATE_TIME_UP, (int) $game['current_level']);

        return $this->state($gameId, true);
    }

    /**
     * The single most important transaction in the system: it decides the
     * result, awards prize money and gifts, and moves the game status - all
     * or nothing.
     */
    public function revealResult(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $level = (int) $game['current_level'];
        if ($level < 1 || $game['current_question_id'] === null) {
            throw new HttpException(409, 'No question is currently on air.');
        }
        if (in_array((string) $game['state'], [self::STATE_CORRECT, self::STATE_WRONG], true)) {
            throw new HttpException(409, 'The result for this question has already been revealed.');
        }

        $timedOut = (string) $game['state'] === self::STATE_TIME_UP
            || ($this->remainingMs($game) <= 0 && (int) $game['is_locked'] !== 1);

        if (!$timedOut
            && (int) $game['is_locked'] !== 1
            && SettingsService::bool('require_lock_before_reveal', true)) {
            throw new HttpException(409, 'Lock the answer before revealing the result.');
        }

        $questionId = (int) $game['current_question_id'];
        $question = $this->questions->find($questionId);
        if ($question === null) {
            throw new HttpException(500, 'The question for this level no longer exists.');
        }

        $gameQuestion = $this->db->selectOne(
            'SELECT * FROM game_questions WHERE game_id = ? AND level_no = ? LIMIT 1',
            [$gameId, $level]
        );
        if ($gameQuestion === null) {
            throw new HttpException(500, 'This level was never served to the participant.');
        }

        $selected = $timedOut && (int) $game['is_locked'] !== 1 ? null : ($game['selected_option'] ?? null);
        $correctOption = (string) $question['correct_option'];
        $isCorrect = $selected !== null && $selected === $correctOption;
        $result = $timedOut && $selected === null ? 'timeout' : ($isCorrect ? 'correct' : 'wrong');

        $timeTaken = max(0, (int) $game['timer_total_ms'] - $this->remainingMs($game));

        return $this->db->transaction(function () use (
            $gameId, $game, $level, $question, $questionId, $gameQuestion,
            $selected, $correctOption, $isCorrect, $result, $timeTaken
        ): array {
            $levelAmount = (float) $gameQuestion['prize_amount'];
            $giftId = $gameQuestion['gift_id'] === null ? null : (int) $gameQuestion['gift_id'];
            $awardedGiftId = null;
            $prizeAwarded = 0.0;

            $isRehearsal = (int) ($game['is_rehearsal'] ?? 0) === 1;

            if ($isCorrect) {
                $prizeAwarded = $levelAmount;
                // A rehearsal never takes a real gift out of stock.
                if ($giftId !== null && !$isRehearsal && $this->gifts->consume($giftId)) {
                    $awardedGiftId = $giftId;
                }
            }

            $existingAnswer = $this->db->selectOne(
                'SELECT id FROM game_answers WHERE game_question_id = ? LIMIT 1',
                [(int) $gameQuestion['id']]
            );

            $answerData = [
                'game_id'          => $gameId,
                'game_question_id' => (int) $gameQuestion['id'],
                'question_id'      => $questionId,
                'level_no'         => $level,
                'selected_option'  => $selected,
                'correct_option'   => $correctOption,
                'is_correct'       => $isCorrect ? 1 : 0,
                'result'           => $result,
                'time_taken_ms'    => $timeTaken,
                'prize_awarded'    => $prizeAwarded,
                'gift_id'          => $awardedGiftId,
                'answered_at'      => date('Y-m-d H:i:s'),
            ];

            if ($existingAnswer === null) {
                $this->db->insert('game_answers', $answerData);
            } else {
                $answerData['was_overridden'] = 1;
                unset($answerData['game_id'], $answerData['game_question_id'], $answerData['question_id']);
                $this->db->update('game_answers', $answerData, ['id' => (int) $existingAnswer['id']]);
            }

            // Rehearsal answers do not skew the question statistics.
            if (!$isRehearsal) {
                $this->questions->recordResult($questionId, $isCorrect);
            }

            $maxLevel = $this->levels->maxLevel();
            $questionsAttempted = (int) ($this->db->scalar(
                'SELECT COUNT(*) FROM game_answers WHERE game_id = ?',
                [$gameId]
            ) ?? 0);
            $questionsCorrect = (int) ($this->db->scalar(
                'SELECT COUNT(*) FROM game_answers WHERE game_id = ? AND is_correct = 1',
                [$gameId]
            ) ?? 0);

            $highestCorrectLevel = (int) ($this->db->scalar(
                'SELECT COALESCE(MAX(level_no), 0) FROM game_answers WHERE game_id = ? AND is_correct = 1',
                [$gameId]
            ) ?? 0);

            $currentWinnings = $this->levels->amountForLevel($highestCorrectLevel);
            $guaranteed = $this->levels->guaranteedAmountUpTo($highestCorrectLevel);

            $update = [
                'questions_attempted' => $questionsAttempted,
                'questions_correct'   => $questionsCorrect,
                'current_winnings'    => $currentWinnings,
                'guaranteed_amount'   => $guaranteed,
                'timer_running'       => 0,
                'timer_started_at'    => null,
                'state'               => $isCorrect ? self::STATE_CORRECT : self::STATE_WRONG,
            ];

            if ($isCorrect) {
                if ($level >= $maxLevel) {
                    // The participant has cleared the whole ladder.
                    $update['state']       = self::STATE_COMPLETED;
                    $update['status']      = 'completed';
                    $update['final_prize'] = $currentWinnings;
                    $update['ended_at']    = date('Y-m-d H:i:s');
                }
            } else {
                $endsGame = $result === 'timeout'
                    ? SettingsService::bool('timeup_ends_game', true)
                    : SettingsService::bool('wrong_answer_ends_game', true);

                if ($endsGame) {
                    $update['status']      = $result === 'timeout' ? 'time_up' : 'wrong_answer';
                    $update['final_prize'] = $guaranteed;
                    $update['ended_at']    = date('Y-m-d H:i:s');
                }
            }

            $this->games->updateById($gameId, $update);
            $this->games->bumpVersion($gameId);
            $this->games->logEvent($gameId, 'result.revealed', $update['state'], $level, [
                'selected'  => $selected,
                'correct'   => $correctOption,
                'result'    => $result,
                'prize'     => $prizeAwarded,
                'gift_id'   => $awardedGiftId,
            ]);

            AuditService::log(
                'game.result_revealed',
                sprintf('Level %d revealed as %s (selected %s, correct %s)', $level, $result, $selected ?? '-', $correctOption),
                'game',
                $gameId
            );

            return $this->state($gameId, true);
        });
    }

    public function nextQuestion(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $level = (int) $game['current_level'];

        if (!in_array((string) $game['state'], [self::STATE_CORRECT, self::STATE_WRONG, self::STATE_TIME_UP, self::STATE_COMPLETED], true)) {
            throw new HttpException(409, 'Reveal the result before moving to the next question.');
        }
        if (in_array((string) $game['status'], ['completed', 'wrong_answer', 'time_up', 'quit', 'abandoned'], true)) {
            throw new HttpException(409, 'This game has finished. Start a new game to continue.');
        }

        $next = $level + 1;
        if ($next > $this->levels->maxLevel()) {
            return $this->completeGame($gameId);
        }

        return $this->loadLevel($gameId, $next);
    }

    /**
     * Steps back to the previous level so an operator mistake can be redone.
     * The recorded answer for that level is removed and the winnings are
     * recalculated - always inside a transaction and always audited.
     */
    public function previousQuestion(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $level = (int) $game['current_level'];
        if ($level <= 1) {
            throw new HttpException(409, 'This is already the first question.');
        }
        $previous = $level - 1;

        $this->db->transaction(function () use ($gameId, $previous): void {
            $gameQuestion = $this->db->selectOne(
                'SELECT id FROM game_questions WHERE game_id = ? AND level_no = ? LIMIT 1',
                [$gameId, $previous]
            );
            if ($gameQuestion !== null) {
                $answer = $this->db->selectOne(
                    'SELECT * FROM game_answers WHERE game_question_id = ? LIMIT 1',
                    [(int) $gameQuestion['id']]
                );
                if ($answer !== null) {
                    // Return the gift to stock if one was handed out.
                    if ($answer['gift_id'] !== null) {
                        $this->db->run(
                            'UPDATE gifts SET quantity_used = GREATEST(quantity_used - 1, 0), status = CASE WHEN status = \'out_of_stock\' THEN \'active\' ELSE status END WHERE id = ?',
                            [(int) $answer['gift_id']]
                        );
                    }
                    $this->db->delete('game_answers', ['id' => (int) $answer['id']]);
                }
            }

            $highestCorrectLevel = (int) ($this->db->scalar(
                'SELECT COALESCE(MAX(level_no), 0) FROM game_answers WHERE game_id = ? AND is_correct = 1',
                [$gameId]
            ) ?? 0);

            $this->games->updateById($gameId, [
                'questions_attempted' => (int) ($this->db->scalar('SELECT COUNT(*) FROM game_answers WHERE game_id = ?', [$gameId]) ?? 0),
                'questions_correct'   => (int) ($this->db->scalar('SELECT COUNT(*) FROM game_answers WHERE game_id = ? AND is_correct = 1', [$gameId]) ?? 0),
                'current_winnings'    => $this->levels->amountForLevel($highestCorrectLevel),
                'guaranteed_amount'   => $this->levels->guaranteedAmountUpTo($highestCorrectLevel),
                'status'              => 'running',
                'final_prize'         => 0,
                'ended_at'            => null,
            ]);
        });

        $this->games->logEvent($gameId, 'question.previous', self::STATE_QUESTION, $previous);
        AuditService::log('game.previous_question', 'Went back to level ' . $previous . ' and cleared its recorded answer.', 'game', $gameId);

        return $this->loadLevel($gameId, $previous);
    }

    public function completeGame(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $this->games->updateById($gameId, [
            'status'      => 'completed',
            'state'       => self::STATE_COMPLETED,
            'final_prize' => (float) $game['current_winnings'],
            'ended_at'    => date('Y-m-d H:i:s'),
            'timer_running' => 0,
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'game.completed', self::STATE_COMPLETED, (int) $game['current_level']);
        AuditService::log('game.completed', 'Game completed with ' . SettingsService::money((float) $game['current_winnings']), 'game', $gameId);

        return $this->state($gameId, true);
    }

    public function quitGame(int $gameId): array
    {
        if (!SettingsService::bool('allow_quit', true)) {
            throw new HttpException(403, 'Quitting is disabled in settings.');
        }
        $game = $this->mustFind($gameId);

        $this->games->updateById($gameId, [
            'status'        => 'quit',
            'state'         => self::STATE_COMPLETED,
            'final_prize'   => (float) $game['current_winnings'],
            'ended_at'      => date('Y-m-d H:i:s'),
            'timer_running' => 0,
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'game.quit', self::STATE_COMPLETED, (int) $game['current_level']);
        AuditService::log('game.quit', 'Participant quit with ' . SettingsService::money((float) $game['current_winnings']), 'game', $gameId);

        return $this->state($gameId, true);
    }

    public function endGame(int $gameId, string $status = 'abandoned'): array
    {
        if (!in_array($status, ['completed', 'abandoned', 'quit'], true)) {
            $status = 'abandoned';
        }
        $game = $this->mustFind($gameId);

        $this->games->updateById($gameId, [
            'status'        => $status,
            'state'         => self::STATE_COMPLETED,
            'final_prize'   => $status === 'abandoned' ? (float) $game['guaranteed_amount'] : (float) $game['current_winnings'],
            'ended_at'      => date('Y-m-d H:i:s'),
            'timer_running' => 0,
        ]);
        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'game.ended', self::STATE_COMPLETED, (int) $game['current_level'], ['status' => $status]);
        AuditService::log('game.ended', 'Game ended with status "' . $status . '".', 'game', $gameId);

        return $this->state($gameId, true);
    }

    /**
     * Reset the current game back to the start. Audit history and the game
     * record itself are preserved - only the play state is cleared.
     */
    public function resetGame(int $gameId, bool $startNew = false): array
    {
        $game = $this->mustFind($gameId);

        $this->db->transaction(function () use ($gameId): void {
            // Give back any gifts awarded in this game.
            foreach ($this->db->select('SELECT gift_id FROM game_answers WHERE game_id = ? AND gift_id IS NOT NULL', [$gameId]) as $row) {
                $this->db->run(
                    'UPDATE gifts SET quantity_used = GREATEST(quantity_used - 1, 0), status = CASE WHEN status = \'out_of_stock\' THEN \'active\' ELSE status END WHERE id = ?',
                    [(int) $row['gift_id']]
                );
            }
            $this->db->run('DELETE FROM game_answers WHERE game_id = ?', [$gameId]);
            $this->db->run('DELETE FROM game_lifelines WHERE game_id = ?', [$gameId]);
            $this->db->run('DELETE FROM game_questions WHERE game_id = ?', [$gameId]);

            $this->games->updateById($gameId, [
                'status'              => 'pending',
                'state'               => self::STATE_INTRO,
                'current_level'       => 0,
                'current_question_id' => null,
                'selected_option'     => null,
                'is_locked'           => 0,
                'timer_running'       => 0,
                'timer_started_at'    => null,
                'timer_remaining_ms'  => 0,
                'timer_total_ms'      => 0,
                'questions_attempted' => 0,
                'questions_correct'   => 0,
                'current_winnings'    => 0,
                'guaranteed_amount'   => 0,
                'final_prize'         => 0,
                'started_at'          => null,
                'ended_at'            => null,
            ]);
        });

        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'game.reset', self::STATE_INTRO, 0);
        AuditService::log('game.reset', 'Reset game ' . $game['game_code'] . ' back to the start.', 'game', $gameId);

        if ($startNew) {
            return $this->startGame($gameId);
        }
        return $this->state($gameId, true);
    }

    // -----------------------------------------------------------------
    // Lifelines
    // -----------------------------------------------------------------

    /**
     * @return array<string,mixed> The refreshed state including the lifeline result.
     */
    public function useLifeline(int $gameId, string $code): array
    {
        $game = $this->mustFind($gameId);
        $this->assertQuestionActive($game);

        if ((int) $game['is_locked'] === 1) {
            throw new HttpException(409, 'The answer is locked - lifelines are no longer available.');
        }

        $lifeline = $this->lifelines->findByCode($code);
        if ($lifeline === null || (int) $lifeline['is_enabled'] !== 1) {
            throw new HttpException(404, 'That lifeline is not available.');
        }

        $questionId = (int) $game['current_question_id'];
        $question = $this->questions->findWithOptions($questionId);
        if ($question === null) {
            throw new HttpException(500, 'The current question could not be loaded.');
        }
        if ((int) $question['lifelines_allowed'] !== 1) {
            throw new HttpException(409, 'Lifelines are disabled for this question.');
        }

        $used = (int) ($this->db->scalar(
            'SELECT COUNT(*) FROM game_lifelines WHERE game_id = ? AND lifeline_id = ?',
            [$gameId, (int) $lifeline['id']]
        ) ?? 0);
        if ($used >= (int) $lifeline['uses_per_game']) {
            throw new HttpException(409, 'The ' . $lifeline['name'] . ' lifeline has already been used.');
        }

        // Options already taken off the board by 50:50 must not reappear in a
        // poll bar or be suggested by the expert.
        $config = $lifeline['config'];
        $config['_removed'] = $this->removedOptions($gameId, $questionId);

        $payload = match ($code) {
            'fifty_fifty'    => $this->buildFiftyFifty($question, $config),
            'audience_poll'  => $this->buildAudiencePoll($question, $config, $gameId),
            'expert_advice'  => $this->buildExpertAdvice($question, $config),
            'phone_a_friend' => $this->buildPhoneAFriend($config),
            'skip_question'  => ['mode' => 'announce', 'seconds' => 4, 'switched' => true],
            default          => [],
        };

        $this->db->insert('game_lifelines', [
            'game_id'       => $gameId,
            'lifeline_id'   => (int) $lifeline['id'],
            'lifeline_code' => $code,
            'question_id'   => $questionId,
            'level_no'      => (int) $game['current_level'],
            'payload'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'used_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'lifeline.used', (string) $game['state'], (int) $game['current_level'], [
            'code' => $code,
        ]);
        AuditService::log('game.lifeline_used', 'Used lifeline "' . $lifeline['name'] . '" at level ' . $game['current_level'], 'game', $gameId);

        if ($code === 'skip_question') {
            return $this->skipCurrentQuestion($gameId);
        }

        return $this->state($gameId, true);
    }

    /**
     * Replaces the current question with another one at the same level and
     * prize - the participant keeps their position on the ladder.
     */
    private function skipCurrentQuestion(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $level = (int) $game['current_level'];

        $current = (int) ($game['current_question_id'] ?? 0);

        $this->db->transaction(function () use ($gameId, $level, $current): void {
            if ($current > 0) {
                // Remember it, so the swap cannot hand back the same question.
                $this->db->run(
                    'INSERT IGNORE INTO game_switched_questions (game_id, question_id, level_no, created_at)
                     VALUES (?, ?, ?, ?)',
                    [$gameId, $current, $level, date('Y-m-d H:i:s')]
                );
            }
            $this->db->run('DELETE FROM game_questions WHERE game_id = ? AND level_no = ?', [$gameId, $level]);
        });

        $this->games->logEvent($gameId, 'question.skipped', self::STATE_QUESTION, $level);
        $state = $this->loadLevel($gameId, $level);

        // The display attaches a lifeline's panel to the question it was used
        // on. A swap replaces that question, so the record follows it -
        // otherwise the screen never announces the swap that just happened.
        $replacement = (int) ($this->db->scalar(
            'SELECT question_id FROM game_questions WHERE game_id = ? AND level_no = ? LIMIT 1',
            [$gameId, $level]
        ) ?? 0);
        if ($replacement > 0) {
            $this->db->run(
                "UPDATE game_lifelines SET question_id = ?
                 WHERE game_id = ? AND lifeline_code = 'skip_question' AND level_no = ?",
                [$replacement, $gameId, $level]
            );
        }

        return $this->state($gameId, true);
    }

    /**
     * @param array<string,mixed> $question
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function buildFiftyFifty(array $question, array $config): array
    {
        $keep = max(2, (int) ($config['keep_options'] ?? 2));
        $correct = (string) $question['correct_option'];
        $wrong = array_values(array_diff(['A', 'B', 'C', 'D'], [$correct]));
        shuffle($wrong);

        $keepWrong = array_slice($wrong, 0, max(0, $keep - 1));
        $remaining = array_merge([$correct], $keepWrong);
        sort($remaining);

        $removed = array_values(array_diff(['A', 'B', 'C', 'D'], $remaining));

        return ['remaining' => $remaining, 'removed' => $removed];
    }

    /**
     * @param array<string,mixed> $question
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    /**
     * Phone a Friend is purely a stage cue: the friend answers on the phone,
     * so the screen only shows that the lifeline is in play and how long is
     * left. Nothing about the question is revealed.
     */
    private function buildPhoneAFriend(array $config): array
    {
        $seconds = max(10, min(180, (int) ($config['seconds'] ?? 30)));

        return [
            'mode'       => 'announce',
            'seconds'    => $seconds,
            'message'    => (string) ($config['message'] ?? ''),
            'started_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function buildAudiencePoll(array $question, array $config, int $gameId = 0): array
    {
        $mode = (string) ($config['mode'] ?? 'realistic');
        $correct = (string) $question['correct_option'];

        $removed = is_array($config['_removed'] ?? null) ? $config['_removed'] : [];


        // Real votes from the audience always beat a simulated poll.
        if ($gameId > 0 && SettingsService::bool('audience_poll_live', true)) {
            $polls = AudiencePollService::make($this->db);
            $open = $polls->openForGame($gameId);
            if ($open !== null) {
                $live = $polls->results((int) $open['id'], $removed);
                if ($live !== null) {
                    $live['code'] = $open['code'];
                    return $live;
                }
            }
            // Fall through if nobody voted.
        }

        // With a hall full of people, the audience answers for itself: the
        // screen only announces that the lifeline is in play. No invented
        // percentages, which would be worse than none.
        if ($mode === 'announce') {
            return [
                'mode'    => 'announce',
                'seconds' => max(10, min(180, (int) ($config['announce_seconds'] ?? 30))),
            ];
        }

        if ($mode === 'manual') {
            $manual = is_array($config['manual_percentages'] ?? null) ? $config['manual_percentages'] : [];
            $percentages = [];
            foreach (['A', 'B', 'C', 'D'] as $key) {
                $percentages[$key] = in_array($key, $removed, true) ? 0 : max(0, (int) ($manual[$key] ?? 0));
            }
            return ['percentages' => $this->normalisePercentages($percentages), 'mode' => 'manual'];
        }

        // Realistic mode: the crowd mostly gets it right, with noise.
        $min = max(25, min(95, (int) ($config['correct_bias_min'] ?? 45)));
        $max = max($min, min(95, (int) ($config['correct_bias_max'] ?? 75)));

        $available = array_values(array_diff(['A', 'B', 'C', 'D'], $removed));

        $correctShare = random_int($min, $max);
        $percentages = array_fill_keys(['A', 'B', 'C', 'D'], 0);
        $percentages[$correct] = $correctShare;

        $others = array_values(array_diff($available, [$correct]));
        $left = 100 - $correctShare;
        foreach ($others as $index => $key) {
            if ($index === count($others) - 1) {
                $percentages[$key] = $left;
            } else {
                $share = $left <= 0 ? 0 : random_int(0, $left);
                $percentages[$key] = $share;
                $left -= $share;
            }
        }

        return ['percentages' => $this->normalisePercentages($percentages), 'mode' => 'realistic'];
    }

    /**
     * @param array<string,int> $percentages
     * @return array<string,int>
     */
    private function normalisePercentages(array $percentages): array
    {
        $total = array_sum($percentages);
        if ($total === 100 || $total === 0) {
            return $percentages;
        }
        $scaled = [];
        foreach ($percentages as $key => $value) {
            $scaled[$key] = (int) round($value / $total * 100);
        }
        $diff = 100 - array_sum($scaled);
        if ($diff !== 0) {
            $highest = array_search(max($scaled), $scaled, true);
            if (is_string($highest)) {
                $scaled[$highest] += $diff;
            }
        }
        return $scaled;
    }

    /**
     * @param array<string,mixed> $question
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function buildExpertAdvice(array $question, array $config): array
    {
        $mode = (string) ($config['mode'] ?? 'auto');
        $correct = (string) $question['correct_option'];
        $confidence = max(1, min(100, (int) ($config['confidence'] ?? 80)));

        if ($mode === 'manual' && in_array((string) ($config['suggested_option'] ?? ''), ['A', 'B', 'C', 'D'], true)) {
            $suggested = (string) $config['suggested_option'];
        } elseif (random_int(1, 100) <= $confidence) {
            $suggested = $correct;
        } else {
            // The expert is usually, but not always, right.
            $removed = is_array($config['_removed'] ?? null) ? $config['_removed'] : [];
            $wrong = array_values(array_diff(['A', 'B', 'C', 'D'], [$correct], $removed));
            $suggested = $wrong === [] ? $correct : (string) $wrong[array_rand($wrong)];
        }

        return [
            'expert_name'      => (string) ($config['expert_name'] ?? 'Quiz Expert'),
            'expert_photo'     => (string) ($config['expert_photo'] ?? ''),
            'suggested_option' => $suggested,
            'confidence'       => $confidence,
            'message'          => (string) ($config['message'] ?? ''),
        ];
    }

    /**
     * Open live audience voting for the question on air.
     *
     * @return array<string,mixed>
     */
    public function openAudiencePoll(int $gameId): array
    {
        $game = $this->mustFind($gameId);
        $this->assertQuestionActive($game);

        $poll = AudiencePollService::make($this->db)->open(
            $gameId,
            (int) $game['current_question_id'],
            (int) $game['current_level']
        );

        $this->games->bumpVersion($gameId);
        $this->games->logEvent($gameId, 'poll.opened', (string) $game['state'], (int) $game['current_level'], [
            'code' => $poll['code'] ?? '',
        ]);

        return $this->state($gameId, true);
    }

    /** Close voting early. */
    public function closeAudiencePoll(int $gameId): array
    {
        $polls = AudiencePollService::make($this->db);
        $open = $polls->openForGame($gameId);
        if ($open !== null) {
            $polls->close((int) $open['id']);
            $this->games->bumpVersion($gameId);
            $this->games->logEvent($gameId, 'poll.closed', null, null, ['code' => $open['code']]);
        }
        return $this->state($gameId, true);
    }

    /** @return array<int,string> Options removed by a 50:50 on this question. */
    private function removedOptions(int $gameId, int $questionId): array
    {
        $row = $this->db->selectOne(
            "SELECT payload FROM game_lifelines
             WHERE game_id = ? AND question_id = ? AND lifeline_code = 'fifty_fifty'
             ORDER BY id DESC LIMIT 1",
            [$gameId, $questionId]
        );
        if ($row === null) {
            return [];
        }
        $payload = json_decode((string) $row['payload'], true);
        return is_array($payload['removed'] ?? null) ? array_values($payload['removed']) : [];
    }

    // -----------------------------------------------------------------
    // State
    // -----------------------------------------------------------------

    /**
     * Build the authoritative state object.
     *
     * @param bool $includePrivate When false the payload is safe to send to
     *                             the public display screen - it never
     *                             contains the correct answer before reveal.
     * @return array<string,mixed>
     */
    public function state(?int $gameId = null, bool $includePrivate = false): array
    {
        $game = $gameId === null ? $this->games->activeGame() : $this->games->findDetailed($gameId);

        if ($game === null) {
            $game = $this->games->latestGame();
        }
        if ($game === null) {
            return $this->idleState($includePrivate);
        }

        // Lazily apply an expired timer so the server stays authoritative
        // even when nobody clicked anything.
        if ((int) $game['timer_running'] === 1 && $this->remainingMs($game) <= 0) {
            $this->markTimeUpInternal((int) $game['id']);
            $game = $this->games->findDetailed((int) $game['id']) ?? $game;
        }

        $gameIdInt = (int) $game['id'];
        $level = (int) $game['current_level'];
        $state = (string) $game['state'];
        $revealed = in_array($state, [self::STATE_CORRECT, self::STATE_WRONG, self::STATE_REVEALED], true)
            || (string) $game['status'] === 'completed';

        $question = null;
        if ($game['current_question_id'] !== null) {
            $question = $this->questions->findWithOptions((int) $game['current_question_id']);
        }

        $removed = $question === null ? [] : $this->removedOptions($gameIdInt, (int) $question['id']);
        $levelRow = $level > 0 ? $this->levels->findByLevel($level) : null;
        $nextLevelRow = $this->levels->findByLevel($level + 1);

        $payload = [
            'has_game'       => true,
            'game_id'        => $gameIdInt,
            'game_code'      => (string) $game['game_code'],
            'status'         => (string) $game['status'],
            'state'          => $state,
            'state_version'  => (int) $game['state_version'],
            'server_time'    => round(microtime(true), 3),
            'is_finished'    => in_array((string) $game['status'], ['completed', 'wrong_answer', 'time_up', 'quit', 'abandoned'], true),
            'is_rehearsal'   => (int) ($game['is_rehearsal'] ?? 0) === 1,

            'participant'    => [
                'id'      => $game['participant_id'] === null ? null : (int) $game['participant_id'],
                'name'    => (string) ($game['participant_name'] ?? 'Participant'),
                'city'    => (string) ($game['participant_city'] ?? ''),
                'photo'   => \App\Core\Application::uploadUrl($game['participant_photo'] ?? null),
                'reg_no'  => (string) ($game['registration_no'] ?? ''),
            ],

            'level'          => $level,
            'total_levels'   => $this->levels->maxLevel(),
            'question_number'=> $level,

            'prize' => [
                'current_amount'      => $levelRow === null ? 0.0 : (float) $levelRow['amount'],
                'current_label'       => $levelRow === null ? '' : SettingsService::money((float) $levelRow['amount']),
                'next_amount'         => $nextLevelRow === null ? null : (float) $nextLevelRow['amount'],
                'next_label'          => $nextLevelRow === null ? '' : SettingsService::money((float) $nextLevelRow['amount']),
                'won_so_far'          => (float) $game['current_winnings'],
                'won_so_far_label'    => SettingsService::money((float) $game['current_winnings']),
                'guaranteed'          => (float) $game['guaranteed_amount'],
                'guaranteed_label'    => SettingsService::money((float) $game['guaranteed_amount']),
                'final_prize'         => (float) $game['final_prize'],
                'final_prize_label'   => SettingsService::money((float) $game['final_prize']),
                'is_guaranteed_level' => $levelRow !== null && (int) $levelRow['is_guaranteed'] === 1,
                'gift_name'           => $levelRow === null ? null : ($levelRow['gift_name'] ?? null),
                'gift_image'          => $levelRow === null ? '' : \App\Core\Application::uploadUrl($levelRow['gift_image'] ?? null),
            ],

            'timer' => [
                'running'      => (int) $game['timer_running'] === 1,
                'remaining_ms' => $this->remainingMs($game),
                'total_ms'     => (int) $game['timer_total_ms'],
                'expired'      => $this->remainingMs($game) <= 0 && (int) $game['timer_total_ms'] > 0,
            ],

            'answer' => [
                'selected'  => $game['selected_option'],
                'locked'    => (int) $game['is_locked'] === 1,
                'revealed'  => $revealed,
                // The correct answer only ever leaves the server after reveal.
                'correct'   => $revealed && $question !== null ? (string) $question['correct_option'] : null,
                'is_correct'=> $revealed ? ($state === self::STATE_CORRECT) : null,
            ],

            'question' => $question === null ? null : [
                'id'         => (int) $question['id'],
                'text'       => (string) $question['question_text'],
                'options'    => $this->visibleOptions($question['options'], $removed),
                'removed'    => $removed,
                'category'   => (string) ($question['category_name'] ?? ''),
                'difficulty' => (string) $question['difficulty'],
                'image'      => \App\Core\Application::uploadUrl($question['image_path'] ?? null),
                'audio'      => \App\Core\Application::uploadUrl($question['audio_path'] ?? null),
                'video'      => \App\Core\Application::uploadUrl($question['video_path'] ?? null),
                'lifelines_allowed' => (int) $question['lifelines_allowed'] === 1,
                // Explanation is part of the reveal, never before.
                'explanation'=> $revealed ? (string) ($question['explanation'] ?? '') : null,
            ],

            'poll'      => $this->pollState($gameIdInt),
            'lifelines' => $this->lifelineState($gameIdInt, (int) ($question['id'] ?? 0)),
            'ladder'    => $this->ladderState($game),
            'gifts_won' => $this->games->giftsWon($gameIdInt),
            'stats'     => [
                'attempted' => (int) $game['questions_attempted'],
                'correct'   => (int) $game['questions_correct'],
            ],
        ];

        if ($includePrivate) {
            $payload['private'] = [
                'correct_option' => $question === null ? null : (string) $question['correct_option'],
                'explanation'    => $question === null ? '' : (string) ($question['explanation'] ?? ''),
                'all_options'    => $question === null ? [] : $question['options'],
                'operator_name'  => (string) ($game['operator_name'] ?? ''),
                'question_time_limit' => (int) $game['timer_total_ms'] / 1000,
            ];
        }

        return $payload;
    }

    /** Safe payload for the audience display - no private keys at all. */
    public function displayState(): array
    {
        $state = $this->state(null, false);
        unset($state['private']);
        return $state;
    }

    /** @return array<string,mixed> */
    private function idleState(bool $includePrivate): array
    {
        return [
            'has_game'      => false,
            'game_id'       => null,
            'status'        => 'idle',
            'state'         => self::STATE_NOT_STARTED,
            'state_version' => 0,
            'server_time'   => round(microtime(true), 3),
            'is_finished'   => false,
            'is_rehearsal'  => false,
            'participant'   => null,
            'level'         => 0,
            'total_levels'  => $this->levels->maxLevel(),
            'question'      => null,
            'timer'         => ['running' => false, 'remaining_ms' => 0, 'total_ms' => 0, 'expired' => false],
            'answer'        => ['selected' => null, 'locked' => false, 'revealed' => false, 'correct' => null, 'is_correct' => null],
            'prize'         => [
                'current_amount' => 0.0, 'current_label' => SettingsService::money(0),
                'next_amount' => null, 'next_label' => '',
                'won_so_far' => 0.0, 'won_so_far_label' => SettingsService::money(0),
                'guaranteed' => 0.0, 'guaranteed_label' => SettingsService::money(0),
                'final_prize' => 0.0, 'final_prize_label' => SettingsService::money(0),
                'is_guaranteed_level' => false, 'gift_name' => null, 'gift_image' => '',
            ],
            'poll'          => null,
            'lifelines'     => $this->lifelineState(0, 0),
            'ladder'        => $this->ladderState(null),
            'gifts_won'     => [],
            'stats'         => ['attempted' => 0, 'correct' => 0],
            'private'       => $includePrivate ? ['correct_option' => null, 'explanation' => '', 'all_options' => []] : null,
        ];
    }

    /**
     * @param array<string,string> $options
     * @param array<int,string> $removed
     * @return array<string,string|null>
     */
    private function visibleOptions(array $options, array $removed): array
    {
        $visible = [];
        foreach (['A', 'B', 'C', 'D'] as $key) {
            $visible[$key] = in_array($key, $removed, true) ? null : ($options[$key] ?? '');
        }
        return $visible;
    }

    /**
     * Live audience voting status for the display and the operator.
     * Never includes individual votes - only the code and the count.
     *
     * @return array<string,mixed>|null
     */
    private function pollState(int $gameId): ?array
    {
        if ($gameId === 0 || !SettingsService::bool('audience_poll_live', true)) {
            return null;
        }
        $open = AudiencePollService::make($this->db)->openForGame($gameId);
        if ($open === null) {
            return null;
        }
        return [
            'code'        => $open['code'],
            'status'      => $open['status'],
            'closes_in'   => $open['closes_in'],
            'total_votes' => $open['total_votes'],
            'qr_url'      => \App\Core\Application::url('/qr?for=vote&code=' . urlencode((string) $open['code'])),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function lifelineState(int $gameId, int $questionId): array
    {
        $out = [];
        foreach ($this->lifelines->enabled() as $lifeline) {
            $usedRows = $gameId === 0 ? [] : $this->db->select(
                'SELECT payload, question_id FROM game_lifelines WHERE game_id = ? AND lifeline_id = ? ORDER BY id',
                [$gameId, (int) $lifeline['id']]
            );

            $payload = null;
            foreach ($usedRows as $row) {
                // Only surface the result while the same question is on air.
                if ((int) $row['question_id'] === $questionId && $questionId > 0) {
                    $decoded = json_decode((string) $row['payload'], true);
                    if (is_array($decoded)) {
                        $payload = $decoded;
                    }
                }
            }

            $out[] = [
                'code'      => (string) $lifeline['code'],
                'name'      => (string) $lifeline['name'],
                'icon'      => (string) $lifeline['icon'],
                'uses'      => (int) $lifeline['uses_per_game'],
                'used'      => count($usedRows),
                'available' => count($usedRows) < (int) $lifeline['uses_per_game'],
                'result'    => $payload,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed>|null $game
     * @return array<int,array<string,mixed>>
     */
    private function ladderState(?array $game): array
    {
        $currentLevel = $game === null ? 0 : (int) $game['current_level'];
        $wonLevel = 0;
        if ($game !== null) {
            $wonLevel = (int) ($this->db->scalar(
                'SELECT COALESCE(MAX(level_no), 0) FROM game_answers WHERE game_id = ? AND is_correct = 1',
                [(int) $game['id']]
            ) ?? 0);
        }

        $ladder = [];
        foreach ($this->levels->ladder() as $row) {
            $levelNo = (int) $row['level_no'];
            $ladder[] = [
                'level'        => $levelNo,
                'amount'       => (float) $row['amount'],
                'label'        => SettingsService::money((float) $row['amount']),
                'short_label'  => \App\Support\Money::shortLabel((float) $row['amount'], SettingsService::currency()),
                'guaranteed'   => (int) $row['is_guaranteed'] === 1,
                'gift_name'    => $row['gift_name'] ?? null,
                'is_current'   => $levelNo === $currentLevel,
                'is_won'       => $levelNo <= $wonLevel,
            ];
        }
        return $ladder;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $game */
    public function remainingMs(array $game): int
    {
        $remaining = (int) $game['timer_remaining_ms'];
        if ((int) $game['timer_running'] !== 1 || $game['timer_started_at'] === null) {
            return max(0, $remaining);
        }
        $elapsed = (int) round((microtime(true) - (float) $game['timer_started_at']) * 1000);
        return max(0, $remaining - $elapsed);
    }

    private function markTimeUpInternal(int $gameId): void
    {
        $this->db->run(
            'UPDATE games SET timer_running = 0, timer_started_at = NULL, timer_remaining_ms = 0,
                    state = ?, state_version = state_version + 1, updated_at = ?
             WHERE id = ? AND timer_running = 1',
            [self::STATE_TIME_UP, date('Y-m-d H:i:s'), $gameId]
        );
        $this->games->logEvent($gameId, 'timer.expired', self::STATE_TIME_UP, null);
    }

    private function now(): float
    {
        return round(microtime(true), 3);
    }

    /** @return array<string,mixed> */
    private function mustFind(int $gameId): array
    {
        $game = $this->games->findDetailed($gameId);
        if ($game === null) {
            throw new HttpException(404, 'Game not found.');
        }
        return $game;
    }

    /** @param array<string,mixed> $game */
    private function assertQuestionActive(array $game): void
    {
        if ($game['current_question_id'] === null || (int) $game['current_level'] < 1) {
            throw new HttpException(409, 'No question is currently on air.');
        }
        if (in_array((string) $game['status'], ['completed', 'wrong_answer', 'time_up', 'quit', 'abandoned'], true)) {
            throw new HttpException(409, 'This game has finished.');
        }
        if (in_array((string) $game['state'], [self::STATE_CORRECT, self::STATE_WRONG], true)) {
            throw new HttpException(409, 'The result has been revealed. Move to the next question.');
        }
    }

    public function games(): GameRepository
    {
        return $this->games;
    }
}
