<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Support\Csv;

final class LogController extends Controller
{
    /** System error log viewer. */
    public function index(Request $request): Response
    {
        $files = Logger::files();
        $selected = $request->string('file');
        $paths = array_map('basename', $files);

        if ($selected === '' || !in_array($selected, $paths, true)) {
            $selected = $paths[0] ?? '';
        }

        $content = '';
        if ($selected !== '') {
            $full = \App\Core\Application::instance()->storagePath('logs/' . $selected);
            $content = Logger::tail($full, 500);
        }

        return $this->view('admin.logs.system', [
            'files'    => $paths,
            'selected' => $selected,
            'content'  => $content,
        ]);
    }

    public function audit(Request $request): Response
    {
        $perPage = 40;
        $page = $this->page($request);
        $filters = [
            'action'  => $request->string('action'),
            'search'  => $request->string('search'),
            'user_id' => $request->int('user_id'),
            'from'    => $request->string('from'),
            'to'      => $request->string('to'),
        ];

        $result = AuditService::paginate($filters, $page, $perPage);

        return $this->view('admin.logs.audit', [
            'logs'       => $result['rows'],
            'pagination' => $this->pagination($result['total'], $page, $perPage, $filters),
            'filters'    => $filters,
            'actions'    => AuditService::actions(),
            'users'      => Database::instance()->select('SELECT id, name FROM users ORDER BY name'),
        ]);
    }

    public function auditCsv(Request $request): Response
    {
        $result = AuditService::paginate([
            'action'  => $request->string('action'),
            'search'  => $request->string('search'),
            'user_id' => $request->int('user_id'),
            'from'    => $request->string('from'),
            'to'      => $request->string('to'),
        ], 1, 10000);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        Csv::put($handle, ['When', 'User', 'Action', 'Entity', 'Description', 'IP address']);
        foreach ($result['rows'] as $row) {
            Csv::put($handle, [
                $row['created_at'],
                $row['user_name'] ?? 'system',
                $row['action'],
                trim(($row['entity_type'] ?? '') . ' ' . ($row['entity_id'] ?? '')),
                $row['description'] ?? '',
                $row['ip_address'] ?? '',
            ]);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return Response::make($csv)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="audit-log-' . date('Y-m-d') . '.csv"');
    }
}
