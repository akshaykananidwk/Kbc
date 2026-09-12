<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Application;

/**
 * Pre-installation environment checks shown by step 1 of the wizard.
 */
final class RequirementsService
{
    /** @return array<string,mixed> */
    public static function check(): array
    {
        $app = Application::instance();

        $php = [
            [
                'label'    => 'PHP 8.2 or newer',
                'current'  => PHP_VERSION,
                'ok'       => version_compare(PHP_VERSION, '8.2.0', '>='),
                'required' => true,
                'hint'     => 'Ask your host to switch the site to PHP 8.2 or newer.',
            ],
        ];

        $extensions = [
            'pdo'       => ['required' => true,  'why' => 'Database access'],
            'pdo_mysql' => ['required' => true,  'why' => 'MySQL / MariaDB driver'],
            'mbstring'  => ['required' => true,  'why' => 'Gujarati and Hindi text handling'],
            'json'      => ['required' => true,  'why' => 'API responses'],
            'openssl'   => ['required' => true,  'why' => 'Encrypting the GitHub token'],
            'fileinfo'  => ['required' => true,  'why' => 'Validating uploaded files'],
            'zip'       => ['required' => true,  'why' => 'Backups and updates'],
            'curl'      => ['required' => false, 'why' => 'GitHub update manager'],
            'gd'        => ['required' => false, 'why' => 'Image handling'],
            'session'   => ['required' => true,  'why' => 'Sign-in sessions'],
        ];

        $extensionRows = [];
        foreach ($extensions as $name => $meta) {
            $extensionRows[] = [
                'label'    => 'Extension: ' . $name,
                'current'  => extension_loaded($name) ? 'loaded' : 'missing',
                'ok'       => extension_loaded($name),
                'required' => $meta['required'],
                'hint'     => $meta['why'],
            ];
        }

        $writables = [];
        foreach ([
            'storage'             => $app->storagePath(),
            'storage/logs'        => $app->storagePath('logs'),
            'storage/cache'       => $app->storagePath('cache'),
            'storage/backups'     => $app->storagePath('backups'),
            'storage/tmp'         => $app->storagePath('tmp'),
            'public/uploads'      => Application::uploadPath(),
            'project root (.env)' => $app->rootPath(),
        ] as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $writables[] = [
                'label'    => 'Writable: ' . $label,
                'current'  => is_writable($path) ? 'writable' : 'not writable',
                'ok'       => is_writable($path),
                'required' => true,
                'hint'     => 'Set this folder to 755 (or 775) so PHP can write to it.',
            ];
        }

        $server = [
            [
                'label'    => 'URL rewriting',
                'current'  => self::rewriteAvailable() ? 'available' : 'could not be detected',
                'ok'       => true,
                'required' => false,
                'hint'     => 'Apache mod_rewrite gives clean URLs. The app still works without it.',
            ],
            [
                'label'    => 'Server software',
                'current'  => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
                'ok'       => true,
                'required' => false,
                'hint'     => '',
            ],
        ];

        $all = array_merge($php, $extensionRows, $writables, $server);

        $failedRequired = array_values(array_filter($all, static fn ($r) => $r['required'] && !$r['ok']));
        $failedOptional = array_values(array_filter($all, static fn ($r) => !$r['required'] && !$r['ok']));

        return [
            'rows'            => $all,
            'php'             => $php,
            'extensions'      => $extensionRows,
            'writables'       => $writables,
            'server'          => $server,
            'can_continue'    => $failedRequired === [],
            'failed_required' => $failedRequired,
            'failed_optional' => $failedOptional,
        ];
    }

    private static function rewriteAvailable(): bool
    {
        if (function_exists('apache_get_modules')) {
            return in_array('mod_rewrite', apache_get_modules(), true);
        }
        // Cannot introspect on FPM/CGI - the .htaccess still applies.
        return true;
    }
}
