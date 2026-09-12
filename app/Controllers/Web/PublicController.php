<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\ParticipantRepository;
use App\Repositories\QuestionRepository;
use App\Services\AudiencePollService;
use App\Services\SettingsService;
use App\Support\QrCode;
use App\Support\Str;

/**
 * Pages the audience reaches on their own phones.
 *
 * Deliberately unauthenticated and deliberately narrow: a voter can only
 * cast a vote, and a registrant can only add themselves. Neither can read
 * the correct answer, see other people's data or touch the game.
 */
final class PublicController extends Controller
{
    /** The voting page a phone lands on after scanning the QR code. */
    public function vote(Request $request): Response
    {
        $code = strtoupper((string) $request->param('code', ''));
        $poll = AudiencePollService::make()->findByCode($code);

        $question = null;
        if ($poll !== null) {
            $row = (new QuestionRepository())->findWithOptions((int) $poll['question_id']);
            if ($row !== null) {
                // Only the wording and the options - never the answer.
                $question = [
                    'text'    => (string) $row['question_text'],
                    'options' => $row['options'],
                ];
            }
        }

        return $this->view('public.vote', [
            'poll'      => $poll,
            'question'  => $question,
            'code'      => $code,
            'siteName'  => SettingsService::string('site_name', 'Ganpati Bapa Quiz Show'),
        ]);
    }

    /** Records a vote. Called by the voting page over AJAX. */
    public function castVote(Request $request): Response
    {
        $code = strtoupper($request->string('code'));
        $option = $request->string('option');
        $token = $request->string('voter_token');

        try {
            $result = AudiencePollService::make()->vote($code, $option, $token, $request->ip());
        } catch (\App\Core\Exceptions\HttpException $e) {
            return $this->fail($e->getMessage(), $e->statusCode());
        }

        return $this->ok('Your vote has been counted.', $result);
    }

    /** Lightweight status so the phone can show the countdown. */
    public function voteStatus(Request $request): Response
    {
        $poll = AudiencePollService::make()->findByCode(strtoupper((string) $request->param('code', '')));
        if ($poll === null) {
            return $this->fail('That vote could not be found.', 404);
        }
        return $this->ok('Poll status.', [
            'status'      => $poll['status'],
            'closes_in'   => $poll['closes_in'],
            'total_votes' => $poll['total_votes'],
        ]);
    }

    /** Public self-registration form reached from a QR code. */
    public function register(Request $request): Response
    {
        if (!SettingsService::bool('public_registration', true)) {
            return $this->view('public.registration-closed', [], 403);
        }

        return $this->view('public.register', [
            'siteName' => SettingsService::string('site_name', 'Ganpati Bapa Quiz Show'),
            'tagline'  => SettingsService::string('site_tagline', ''),
            'ganpati'  => \App\Core\Application::uploadUrl(SettingsService::string('ganpati_image', '')),
        ]);
    }

    public function storeRegistration(Request $request): Response
    {
        if (!SettingsService::bool('public_registration', true)) {
            return $this->fail('Registration is closed.', 403);
        }

        // Honeypot: a real person never fills a hidden field.
        if ($request->string('website') !== '') {
            return $this->ok('Thank you, you are registered.', ['registration_no' => '']);
        }

        try {
            $data = Validator::validate($request->all(), [
                'name'   => 'required|string|min:2|max:150',
                'mobile' => 'required|string|min:6|max:20',
                'city'   => 'nullable|string|max:120',
                'age'    => 'nullable|int|between:5,120',
            ], ['name' => 'Name', 'mobile' => 'Mobile number']);
        } catch (\App\Core\Exceptions\ValidationException $e) {
            return $this->fail($e->getMessage(), 422, $e->errors());
        }

        $db = Database::instance();
        $mobile = preg_replace('/\D+/', '', (string) $data['mobile']) ?? '';

        // Someone hitting submit twice should not create two entries.
        $existing = $db->selectOne(
            'SELECT id, registration_no FROM participants WHERE mobile = ? ORDER BY id DESC LIMIT 1',
            [$mobile]
        );
        if ($existing !== null) {
            return $this->ok('You are already registered.', [
                'registration_no' => (string) $existing['registration_no'],
                'duplicate'       => true,
            ]);
        }

        $repository = new ParticipantRepository();
        $registration = $repository->nextRegistrationNumber();

        $id = $repository->create([
            'name'            => $data['name'],
            'registration_no' => $registration,
            'mobile'          => $mobile,
            'city'            => $data['city'] ?? null,
            'age'             => ($data['age'] ?? 0) > 0 ? (int) $data['age'] : null,
            'status'          => 'active',
            'notes'           => 'Registered from the public QR form.',
        ]);

        \App\Services\AuditService::log('participant.self_registered', 'Public registration: ' . $data['name'], 'participant', $id);

        return $this->ok('You are registered. Please keep your number handy.', [
            'registration_no' => $registration,
            'name'            => $data['name'],
            'duplicate'       => false,
        ]);
    }

    /** The contender's phone page for a Fastest Finger round. */
    public function fff(Request $request): Response
    {
        $roundId = $request->intParam('id');
        $service = \App\Services\FastestFingerService::make();

        $round = null;
        try {
            $round = $service->state($roundId);
        } catch (\App\Core\Exceptions\HttpException) {
            $round = null;
        }

        return $this->view('public.fff', [
            'round'    => $round,
            'roundId'  => $roundId,
            'siteName' => SettingsService::string('site_name', 'Ganpati Bapa Quiz Show'),
        ]);
    }

    /** Records a contender's ordering, identified by their access code. */
    public function submitFff(Request $request): Response
    {
        $roundId = $request->int('round_id', 0);
        $code = $request->string('access_code');
        $order = $request->array('order');
        if ($order === []) {
            $order = str_split(strtoupper($request->string('order_string')));
        }

        try {
            $state = \App\Services\FastestFingerService::make()->submitByCode($roundId, $code, $order);
        } catch (\App\Core\Exceptions\HttpException $e) {
            return $this->fail($e->getMessage(), $e->statusCode());
        }

        // The public state carries no answer, so this is safe to return.
        return $this->ok('Your answer has been recorded.', [
            'answered'   => $state['answered'],
            'contenders' => count($state['contenders']),
        ]);
    }

    /** Confirms a code belongs to this round, so the phone can greet by name. */
    public function checkFffCode(Request $request): Response
    {
        $roundId = $request->int('round_id', 0);
        $contender = \App\Services\FastestFingerService::make()
            ->contenderByCode($roundId, $request->string('access_code'));

        if ($contender === null) {
            return $this->fail('That code is not valid for this round.', 404);
        }

        return $this->ok('Code accepted.', [
            'name'      => (string) $contender['name'],
            'answered'  => $contender['submitted_at'] !== null,
        ]);
    }

    /** Serves a QR code as an SVG for any allowed target. */
    public function qr(Request $request): Response
    {
        $target = $request->string('for', 'register');
        $allowed = ['register', 'vote', 'display', 'fff'];
        if (!in_array($target, $allowed, true)) {
            return Response::text('Unknown QR target.', 404);
        }

        if ($target === 'vote') {
            $code = preg_replace('/[^A-Z0-9]/', '', strtoupper($request->string('code')));
            if ($code === '' || strlen($code) > 12) {
                return Response::text('A valid poll code is required.', 422);
            }
            $url = $this->absoluteUrl('/vote/' . $code);
        } elseif ($target === 'fff') {
            $round = $request->int('round', 0);
            if ($round < 1) {
                return Response::text('A valid round id is required.', 422);
            }
            $url = $this->absoluteUrl('/fff/' . $round);
        } elseif ($target === 'display') {
            $url = $this->absoluteUrl('/display');
        } else {
            $url = $this->absoluteUrl('/register');
        }

        $size = max(3, min(14, $request->int('scale', 8)));

        try {
            $svg = QrCode::svg($url, $size, 4, '#1c1512', '#ffffff');
        } catch (\Throwable $e) {
            return Response::text('The QR code could not be generated.', 500);
        }

        return Response::make($svg)
            ->withHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    private function absoluteUrl(string $path): string
    {
        $configured = (string) (SettingsService::string('site_url', '') ?: \App\Core\Env::get('APP_URL', ''));
        if ($configured !== '') {
            return rtrim($configured, '/') . '/' . ltrim(\App\Core\Application::url($path), '/');
        }
        $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . \App\Core\Application::url($path);
    }
}
