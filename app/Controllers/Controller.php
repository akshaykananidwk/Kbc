<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use App\Services\SettingsService;

abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $shared = [
            'currentUser' => AuthService::user(),
            'csrfToken'   => Csrf::token(),
            'flash'       => Session::pullFlash(),
            'errors'      => Session::pullErrors(),
            'old'         => Session::pullOld(),
            'siteName'    => SettingsService::string('site_name', 'Ganpati Bapa Quiz Show'),
            'siteTagline' => SettingsService::string('site_tagline', ''),
            'siteLogo'    => SettingsService::string('site_logo', ''),
            'favicon'     => SettingsService::string('favicon', ''),
            'footerText'  => SettingsService::string('footer_text', ''),
            'theme'       => [
                'primary'   => SettingsService::string('primary_color', '#b3141a'),
                'secondary' => SettingsService::string('secondary_color', '#f5a623'),
                'accent'    => SettingsService::string('accent_color', '#ffd76e'),
            ],
            'appVersion'  => (string) \App\Core\Config::get('app.version', '1.0.0'),
        ];

        return View::response($template, array_merge($shared, $data), $status);
    }

    /** @param array<string,mixed> $data */
    protected function ok(string $message = 'OK', array $data = []): Response
    {
        return Response::apiSuccess($message, $data);
    }

    /** @param array<string,mixed> $errors */
    protected function fail(string $message, int $status = 400, array $errors = []): Response
    {
        return Response::apiError($message, $status, $errors);
    }

    protected function back(string $fallback = '/admin'): Response
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        return Response::redirect($referer !== '' ? $referer : $fallback);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function success(string $message): void
    {
        Session::flash('success', $message);
    }

    protected function error(string $message): void
    {
        Session::flash('error', $message);
    }

    protected function page(Request $request): int
    {
        return max(1, $request->int('page', 1));
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    protected function pagination(int $total, int $page, int $perPage, array $filters = []): array
    {
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        return [
            'total'    => $total,
            'page'     => min($page, $pages),
            'per_page' => $perPage,
            'pages'    => $pages,
            'from'     => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'to'       => min($total, $page * $perPage),
            'filters'  => array_filter($filters, static fn ($v) => $v !== '' && $v !== null && $v !== 0),
        ];
    }
}
