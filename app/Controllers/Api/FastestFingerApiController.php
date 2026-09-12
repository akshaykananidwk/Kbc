<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\FastestFingerService;

final class FastestFingerApiController extends Controller
{
    private FastestFingerService $service;

    public function __construct()
    {
        $this->service = FastestFingerService::make();
    }

    /** Operator view — includes the correct order. */
    public function state(Request $request): Response
    {
        $id = $request->int('round_id', 0);
        $state = $id > 0 ? $this->service->state($id, true) : null;

        if ($state === null) {
            $current = $this->service->currentRound();
            $state = $current === null ? null : $this->service->state((int) $current['id'], true);
        }

        return $this->ok('Fastest Finger state.', ['round' => $state]);
    }

    public function create(Request $request): Response
    {
        $options = $request->array('options');
        $order = $request->array('correct_order');
        if ($order === []) {
            $order = str_split(strtoupper($request->string('order')));
        }

        $state = $this->service->create(
            $request->string('question'),
            $order,
            $request->array('participants'),
            AuthService::id(),
            $request->int('time_limit', 0) ?: null
        );

        // Option wording is presentation only and lives with the round text.
        if ($options !== []) {
            \App\Core\Database::instance()->update('fff_rounds', [
                'question_text' => $request->string('question') . "\n" . implode("\n", array_map(
                    static fn ($key, $text) => strtoupper((string) $key) . '. ' . $text,
                    array_keys($options),
                    $options
                )),
            ], ['id' => (int) $state['id']]);
            $state = $this->service->state((int) $state['id'], true);
        }

        return $this->ok('Fastest Finger round created.', ['round' => $this->service->state((int) $state['id'], true)]);
    }

    public function start(Request $request): Response
    {
        $id = $this->roundId($request);
        $this->service->start($id);
        return $this->ok('Round started.', ['round' => $this->service->state($id, true)]);
    }

    public function submit(Request $request): Response
    {
        $order = $request->array('order');
        if ($order === []) {
            $order = str_split(strtoupper($request->string('order_string')));
        }

        $id = $this->roundId($request);
        $this->service->submit($id, $request->int('participant_id', 0), $order);

        return $this->ok('Answer recorded.', ['round' => $this->service->state($id, true)]);
    }

    public function close(Request $request): Response
    {
        $id = $this->roundId($request);
        $this->service->close($id);
        return $this->ok('Round closed.', ['round' => $this->service->state($id, true)]);
    }

    public function cancel(Request $request): Response
    {
        $this->service->cancel($this->roundId($request));
        return $this->ok('Round cancelled.');
    }

    public function history(Request $request): Response
    {
        return $this->ok('Recent rounds.', ['rounds' => $this->service->history(25)]);
    }

    private function roundId(Request $request): int
    {
        $id = $request->int('round_id', 0);
        if ($id > 0) {
            return $id;
        }
        $current = $this->service->currentRound();
        if ($current === null) {
            throw new \App\Core\Exceptions\HttpException(404, 'There is no Fastest Finger round open.');
        }
        return (int) $current['id'];
    }
}
