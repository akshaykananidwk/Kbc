<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\BackupService;
use App\Support\Str;

final class BackupController extends Controller
{
    private BackupService $backups;

    public function __construct()
    {
        $this->backups = BackupService::make();
    }

    public function index(Request $request): Response
    {
        $directory = Application::instance()->storagePath('backups');

        return $this->view('admin.backups.index', [
            'backups'   => $this->backups->listBackups(),
            'directory' => $directory,
            'writable'  => is_writable($directory),
            'freeSpace' => Str::humanBytes((float) (@disk_free_space($directory) ?: 0)),
        ]);
    }

    public function createDatabase(Request $request): Response
    {
        try {
            $result = $this->backups->backupDatabase(AuthService::id(), $request->string('note'));
            $this->success('Database backup created: ' . $result['filename'] . ' (' . $result['size_human'] . ')');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        return $this->redirect('/admin/backups');
    }

    public function createFiles(Request $request): Response
    {
        try {
            $result = $this->backups->backupFiles(AuthService::id(), $request->string('note'));
            $this->success('Files backup created: ' . $result['filename'] . ' (' . $result['size_human'] . ', ' . $result['file_count'] . ' files)');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        return $this->redirect('/admin/backups');
    }

    public function download(Request $request): Response
    {
        $id = $request->intParam('id');
        $path = $this->backups->pathFor($id);
        if ($path === null) {
            $this->error('That backup file could not be found on disk.');
            return $this->redirect('/admin/backups');
        }

        \App\Services\AuditService::log('backup.downloaded', 'Downloaded backup #' . $id, 'backup', $id);

        return Response::download($path, basename($path), 'application/zip');
    }

    public function restore(Request $request): Response
    {
        $id = $request->intParam('id');
        $path = $this->backups->pathFor($id);
        if ($path === null) {
            $this->error('That backup file could not be found on disk.');
            return $this->redirect('/admin/backups');
        }

        // Guard: refuse to restore while a game is on air.
        $active = (new \App\Repositories\GameRepository())->activeGame();
        if ($active !== null) {
            $this->error('Game ' . $active['game_code'] . ' is still in progress. End it before restoring a backup.');
            return $this->redirect('/admin/backups');
        }

        try {
            $result = $this->backups->restoreDatabase($path);
            $this->success(
                'Database restored (' . $result['statements'] . ' statements, ' . $result['tables'] . ' tables). '
                . 'A safety backup of the previous data was saved as ' . $result['safety_backup'] . '.'
            );
        } catch (\Throwable $e) {
            $this->error('Restore failed: ' . $e->getMessage());
        }

        return $this->redirect('/admin/backups');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->intParam('id');
        if ($this->backups->delete($id)) {
            $this->success('Backup deleted.');
        } else {
            $this->error('That backup could not be found.');
        }
        return $this->redirect('/admin/backups');
    }
}
