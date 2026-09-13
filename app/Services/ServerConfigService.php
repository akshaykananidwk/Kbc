<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Support\Str;
use App\Support\Uploader;

/**
 * Helps an admin raise PHP's upload limits without leaving the browser.
 *
 * The stock limit is 2 MB, which is smaller than any song, so uploading
 * music is impossible until it is raised. A .user.ini next to the front
 * controller is what PHP-FPM and CGI hosting read; mod_php reads the values
 * in .htaccess instead. Some hosts refuse to let PHP write either, so this
 * service always offers the text to paste as well.
 */
final class ServerConfigService
{
    /** Values the application asks for. */
    public const WANTED = [
        'upload_max_filesize' => '64M',
        'post_max_size'       => '68M',
        'max_execution_time'  => '300',
        'max_input_time'      => '300',
        'memory_limit'        => '256M',
    ];

    public static function userIniPath(): string
    {
        return Application::instance()->rootPath('.user.ini');
    }

    /** The exact file contents, ready to write or to paste over FTP. */
    public static function template(): string
    {
        $lines = [
            '; Ganpati Bapa Quiz Show - PHP limits',
            '; Raised so music and long audio files can be uploaded.',
            '; PHP caches this file for up to five minutes.',
            '',
        ];
        foreach (self::WANTED as $key => $value) {
            $lines[] = $key . ' = ' . $value;
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * What the server currently allows, and whether anything can be done
     * about it from here.
     *
     * @return array{limit:int,limit_human:string,is_low:bool,file_exists:bool,
     *               can_write:bool,path:string,wanted:string}
     */
    public static function status(): array
    {
        $path = self::userIniPath();
        $limit = Uploader::serverLimit();
        $exists = is_file($path);

        return [
            'limit'       => $limit,
            'limit_human' => Str::humanBytes($limit),
            // Under 8 MB a normal song will not fit.
            'is_low'      => $limit < 8 * 1024 * 1024,
            'file_exists' => $exists,
            'can_write'   => $exists ? is_writable($path) : is_writable(dirname($path)),
            'path'        => $path,
            'wanted'      => self::WANTED['upload_max_filesize'],
        ];
    }

    /**
     * Writes .user.ini if the host allows it.
     *
     * @return array{written:bool,message:string}
     */
    public static function writeUserIni(?int $userId = null): array
    {
        $path = self::userIniPath();

        if (is_file($path) && !is_writable($path)) {
            return [
                'written' => false,
                'message' => 'A .user.ini already exists but the server will not let PHP change it. '
                    . 'Edit it over FTP with the text below.',
            ];
        }
        if (!is_file($path) && !is_writable(dirname($path))) {
            return [
                'written' => false,
                'message' => 'The application folder is not writable by PHP, so the file cannot be created here. '
                    . 'Upload it over FTP with the text below, or raise the limits in your hosting control panel.',
            ];
        }
        if (@file_put_contents($path, self::template()) === false) {
            return [
                'written' => false,
                'message' => 'The server refused to write .user.ini. Upload it over FTP with the text below.',
            ];
        }

        @chmod($path, 0644);
        AuditService::log('settings.php_limits', 'Created .user.ini to raise the upload limits.', 'settings', null);

        return [
            'written' => true,
            'message' => '.user.ini created, asking for ' . self::WANTED['upload_max_filesize']
                . ' uploads. PHP caches this file, so allow up to five minutes, then reload this page '
                . 'to see the new limit. If it does not change, your host applies its own limit and you '
                . 'will need to raise it in the hosting control panel.',
        ];
    }
}
