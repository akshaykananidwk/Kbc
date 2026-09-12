<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\GitHubClient;
use App\Services\SettingsService;
use App\Services\UpdateService;
use App\Support\Str;

final class UpdateController extends Controller
{
    public function index(Request $request): Response
    {
        $service = UpdateService::make();
        $client = GitHubClient::fromSettings();

        $token = SettingsService::string('github_token');

        return $this->view('admin.updates.index', [
            'config' => [
                'owner'      => SettingsService::string('github_owner'),
                'repo'       => SettingsService::string('github_repo'),
                'branch'     => SettingsService::string('github_branch', 'main'),
                // Only ever a mask reaches the browser.
                'token_mask' => $token === '' ? '' : 'ghp_' . str_repeat('*', 14),
                'has_token'  => $token !== '',
                'configured' => $client->isConfigured(),
            ],
            'currentVersion' => $service->currentVersion(),
            'currentCommit'  => SettingsService::string('current_commit_sha'),
            'lastChecked'    => SettingsService::string('last_checked_at'),
            'autoBackup'     => SettingsService::bool('auto_backup_before_update', true),
            'protectedPaths' => $service->protectedPaths(),
            'history'        => $service->history(25),
            'curlAvailable'  => function_exists('curl_init'),
            'zipAvailable'   => class_exists('ZipArchive'),
        ]);
    }

    public function saveSettings(Request $request): Response
    {
        $owner  = $request->string('github_owner');
        $repo   = $request->string('github_repo');
        $branch = $request->string('github_branch', 'main');
        $token  = (string) $request->input('github_token', '');

        foreach ([['owner', $owner], ['repository', $repo]] as [$label, $value]) {
            if ($value !== '' && !preg_match('/^[A-Za-z0-9_.-]+$/', $value)) {
                $this->error('The GitHub ' . $label . ' contains invalid characters.');
                return $this->redirect('/admin/updates');
            }
        }
        if ($branch !== '' && !preg_match('#^[A-Za-z0-9._/-]+$#', $branch)) {
            $this->error('The branch name contains invalid characters.');
            return $this->redirect('/admin/updates');
        }

        SettingsService::set('github_owner', $owner);
        SettingsService::set('github_repo', $repo);
        SettingsService::set('github_branch', $branch === '' ? 'main' : $branch);

        // An untouched masked field must not wipe the stored token.
        if ($token !== '' && !preg_match('/^(ghp_)?\*+$/', $token)) {
            SettingsService::set('github_token', $token);
            AuditService::log('update.token_changed', 'Updated the GitHub token (value not logged).', 'settings');
        } elseif ($request->bool('remove_token', false)) {
            SettingsService::set('github_token', '');
            AuditService::log('update.token_removed', 'Removed the stored GitHub token.', 'settings');
        }

        AuditService::log('update.settings_saved', 'Saved GitHub update settings for ' . $owner . '/' . $repo . '@' . $branch, 'settings');
        $this->success('Update settings saved.');
        return $this->redirect('/admin/updates');
    }

    public function show(Request $request): Response
    {
        $update = UpdateService::make()->find($request->intParam('id'));
        if ($update === null) {
            $this->error('That update record could not be found.');
            return $this->redirect('/admin/updates');
        }
        return $this->view('admin.updates.show', ['update' => $update]);
    }
}
