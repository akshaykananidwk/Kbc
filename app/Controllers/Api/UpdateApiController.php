<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CacheService;
use App\Services\UpdateService;

final class UpdateApiController extends Controller
{
    public function check(Request $request): Response
    {
        try {
            $result = UpdateService::make()->checkForUpdate($request->bool('cached', false));
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 502);
        }
        // Belt and braces: the token must never appear in an API response.
        unset($result['token'], $result['github_token']);
        return $this->ok($result['message'] ?? 'Checked for updates.', $result);
    }

    public function run(Request $request): Response
    {
        if (!$request->bool('confirm', false)) {
            return $this->fail('Confirmation is required before an update can run.', 422);
        }

        @set_time_limit(600);

        try {
            $record = UpdateService::make()->runUpdate(AuthService::id());
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }

        return $this->ok('Update completed successfully.', ['update' => $record]);
    }

    public function rollback(Request $request): Response
    {
        $id = $request->int('update_id', 0);
        if ($id < 1) {
            return $this->fail('Choose which update to roll back.', 422);
        }
        try {
            $restored = UpdateService::make()->rollback($id);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
        return $this->ok('Rollback completed. ' . $restored . ' file(s) restored.', ['restored' => $restored]);
    }

    public function history(Request $request): Response
    {
        return $this->ok('Update history.', ['updates' => UpdateService::make()->history(50)]);
    }

    public function clearCache(Request $request): Response
    {
        $count = CacheService::clearAll();
        \App\Services\AuditService::log('cache.cleared', 'Cleared the application cache (' . $count . ' entries).', 'system');
        return $this->ok('Cache cleared. ' . $count . ' entr(ies) removed. Uploads and the database were not touched.', ['removed' => $count]);
    }
}
