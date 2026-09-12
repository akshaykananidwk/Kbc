<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Services\SettingsService;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home', [
            'metaDescription' => SettingsService::string('meta_description', ''),
            'ganpatiImage'    => Application::uploadUrl(SettingsService::string('ganpati_image', '')),
            'contactPhone'    => SettingsService::string('contact_phone', ''),
            'contactEmail'    => SettingsService::string('contact_email', ''),
        ]);
    }

    /** Admin, operator and display screens are kept out of search engines. */
    public function robots(Request $request): Response
    {
        $base = Application::basePath();
        $lines = [
            'User-agent: *',
            'Disallow: ' . $base . '/admin',
            'Disallow: ' . $base . '/operator',
            'Disallow: ' . $base . '/display',
            'Disallow: ' . $base . '/api',
            'Disallow: ' . $base . '/install',
            'Allow: ' . ($base === '' ? '/' : $base . '/'),
        ];
        return Response::text(implode("\n", $lines) . "\n");
    }
}
