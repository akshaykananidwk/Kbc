<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CertificateService;

final class CertificateController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.certificates.index', [
            'certificates' => CertificateService::make()->recent(100),
            'enabled'      => \App\Services\SettingsService::bool('certificate_enabled', true),
        ]);
    }

    /** Renders the printable certificate for a finished game. */
    public function show(Request $request): Response
    {
        try {
            $data = CertificateService::make()->issue($request->intParam('id'), AuthService::id());
        } catch (\App\Core\Exceptions\HttpException $e) {
            $this->error($e->getMessage());
            return $this->back('/admin/games');
        }

        return $this->view('admin.certificates.print', [
            'certificate' => $data['certificate'],
            'game'        => $data['game'],
            'gifts'       => $data['gifts'],
            'design'      => $data['design'],
        ]);
    }
}
