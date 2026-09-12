<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\SettingsService;

final class SettingsApiController extends Controller
{
    /** Secret values are never returned - only whether they are configured. */
    public function index(Request $request): Response
    {
        $out = [];
        foreach (SettingsService::groups() as $group) {
            foreach (SettingsService::group($group) as $row) {
                $key = (string) $row['key_name'];
                $out[$group][$key] = (bool) $row['is_secret']
                    ? ['configured' => SettingsService::hasValue($key), 'secret' => true]
                    : SettingsService::get($key);
            }
        }
        return $this->ok('Settings.', ['settings' => $out]);
    }

    public function update(Request $request): Response
    {
        $payload = $request->array('settings');
        if ($payload === []) {
            return $this->fail('No settings were supplied.', 422);
        }

        $allowed = [];
        foreach (SettingsService::groups() as $group) {
            foreach (SettingsService::group($group) as $row) {
                $allowed[(string) $row['key_name']] = true;
            }
        }

        $changes = [];
        foreach ($payload as $key => $value) {
            if (!isset($allowed[(string) $key])) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $changes[(string) $key] = $value;
        }

        if ($changes === []) {
            return $this->fail('None of the supplied keys are valid settings.', 422);
        }

        SettingsService::setMany($changes);
        AuditService::log('settings.updated', 'Updated ' . count($changes) . ' setting(s) via the API.', 'settings');

        return $this->ok('Settings saved.', ['updated' => array_keys($changes)]);
    }
}
