<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Application;
use App\Core\Exceptions\ValidationException;

/**
 * Hardened upload handling: extension allow-list, MIME sniffing, size caps,
 * randomised filenames and a hard block on anything executable.
 */
final class Uploader
{
    /** @var array<string,array<int,string>> */
    private const ALLOWED = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
        'video' => ['mp4', 'webm'],
        'icon'  => ['ico', 'png', 'svg'],
    ];

    /** @var array<string,array<int,string>> */
    private const MIME_MAP = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'svg'  => ['image/svg+xml', 'text/plain', 'text/xml', 'application/xml'],
        'ico'  => ['image/vnd.microsoft.icon', 'image/x-icon', 'image/png'],
        'mp3'  => ['audio/mpeg', 'audio/mp3', 'application/octet-stream'],
        'wav'  => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        'ogg'  => ['audio/ogg', 'video/ogg', 'application/ogg'],
        'm4a'  => ['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/octet-stream'],
        'mp4'  => ['video/mp4', 'application/octet-stream'],
        'webm' => ['video/webm', 'audio/webm'],
    ];

    /** Extensions that must never be written, whatever the allow-list says. */
    private const FORBIDDEN = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'dll', 'so', 'jsp', 'asp',
        'aspx', 'htaccess', 'htpasswd', 'ini', 'conf', 'js', 'html', 'htm',
    ];

    /**
     * The largest upload PHP itself will accept, whatever the application
     * would allow. On stock hosting this is 2 MB - smaller than any song -
     * which is why music uploads appear to "do nothing" until it is raised.
     */
    public static function serverLimit(): int
    {
        $upload = self::iniBytes((string) ini_get('upload_max_filesize'));
        $post   = self::iniBytes((string) ini_get('post_max_size'));

        $limits = array_filter([$upload, $post], static fn (int $v): bool => $v > 0);
        return $limits === [] ? PHP_INT_MAX : (int) min($limits);
    }

    /** Turns "8M", "512K" or "1G" into bytes. */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    /** How to raise the server limit, in words an admin can act on. */
    public static function serverLimitAdvice(): string
    {
        return 'Your server currently accepts uploads up to '
            . Str::humanBytes(self::serverLimit())
            . '. To allow larger music files, raise upload_max_filesize and post_max_size: '
            . 'Settings -> Sound has a button that writes a .user.ini for you, and shows the '
            . 'text to upload by hand if your host will not let PHP write it.';
    }

    /**
     * @param array<string,mixed> $file  A single entry from $_FILES
     * @param array<int,string>   $types One or more keys of self::ALLOWED
     * @return array{path:string,url:string,filename:string,mime:string,size:int,extension:string}
     */
    public static function store(array $file, string $subDirectory, array $types = ['image'], int $maxBytes = 5242880): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException(['upload' => self::errorMessage($error)], self::errorMessage($error));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || (!is_uploaded_file($tmp) && !Application::isTesting())) {
            throw new ValidationException(['upload' => 'The upload could not be verified.'], 'The upload could not be verified.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new ValidationException(['upload' => 'The uploaded file is empty.'], 'The uploaded file is empty.');
        }
        if ($size > $maxBytes) {
            $message = 'The file is larger than the ' . Str::humanBytes($maxBytes) . ' limit.';
            if ($maxBytes >= self::serverLimit()) {
                $message .= ' ' . self::serverLimitAdvice();
            }
            throw new ValidationException(['upload' => $message], $message);
        }

        $originalName = (string) ($file['name'] ?? 'upload');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || in_array($extension, self::FORBIDDEN, true)) {
            throw new ValidationException(['upload' => 'This file type is not allowed.'], 'This file type is not allowed.');
        }

        $allowed = [];
        foreach ($types as $type) {
            $allowed = array_merge($allowed, self::ALLOWED[$type] ?? []);
        }
        if (!in_array($extension, $allowed, true)) {
            throw new ValidationException(['upload' => 'Allowed file types: ' . implode(', ', array_unique($allowed)) . '.'], 'Allowed file types: ' . implode(', ', array_unique($allowed)) . '.');
        }

        // Reject double extensions such as "photo.php.jpg".
        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
        foreach (explode('.', strtolower($nameWithoutExt)) as $part) {
            if (in_array($part, self::FORBIDDEN, true)) {
                throw new ValidationException(['upload' => 'This file name is not allowed.'], 'This file name is not allowed.');
            }
        }

        $mime = self::detectMime($tmp);
        $expected = self::MIME_MAP[$extension] ?? [];
        if ($expected !== [] && !in_array($mime, $expected, true)) {
            throw new ValidationException(['upload' => 'The file content does not match its extension.'], 'The file content does not match its extension.');
        }

        // Raster images must be decodable; SVG is sanitised instead.
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $info = @getimagesize($tmp);
            if ($info === false) {
                throw new ValidationException(['upload' => 'The image file is corrupted or not a real image.'], 'The image file is corrupted or not a real image.');
            }
        }

        // Never store a file that contains PHP markers.
        if (self::containsPhp($tmp)) {
            throw new ValidationException(['upload' => 'The file contains code and was rejected.'], 'The file contains code and was rejected.');
        }

        $directory = rtrim(Application::uploadPath(), '/') . '/' . trim($subDirectory, '/');
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ValidationException(['upload' => 'Upload directory is not writable: ' . $subDirectory], 'Upload directory is not writable: ' . $subDirectory);
        }

        $filename = date('Ymd') . '-' . Str::random(24) . '.' . $extension;
        $target = $directory . '/' . $filename;

        $moved = Application::isTesting()
            ? @rename($tmp, $target)
            : @move_uploaded_file($tmp, $target);

        if (!$moved) {
            throw new ValidationException(['upload' => 'The file could not be saved to disk.'], 'The file could not be saved to disk.');
        }
        @chmod($target, 0644);

        if ($extension === 'svg') {
            self::sanitiseSvg($target);
        }

        $relative = 'uploads/' . trim($subDirectory, '/') . '/' . $filename;

        return [
            'path'      => $target,
            'url'       => $relative,
            'filename'  => $filename,
            'mime'      => $mime,
            'size'      => $size,
            'extension' => $extension,
        ];
    }

    public static function delete(?string $relativePath): bool
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return false;
        }
        $relativePath = ltrim($relativePath, '/');
        if (!str_starts_with($relativePath, 'uploads/')) {
            return false;
        }
        $base = realpath(Application::uploadPath());
        $full = realpath(Application::publicPath() . '/' . $relativePath);
        if ($base === false || $full === false || !str_starts_with($full, $base) || !is_file($full)) {
            return false;
        }
        return @unlink($full);
    }

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        return 'application/octet-stream';
    }

    private static function containsPhp(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return true;
        }
        $head = (string) fread($handle, 8192);
        fclose($handle);
        return stripos($head, '<?php') !== false
            || stripos($head, '<?=') !== false
            || stripos($head, '<script language="php"') !== false;
    }

    /** Strip scripting constructs out of uploaded SVG files. */
    private static function sanitiseSvg(string $path): void
    {
        $content = (string) @file_get_contents($path);
        $content = preg_replace('#<script.*?</script>#is', '', $content) ?? $content;
        $content = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $content) ?? $content;
        $content = preg_replace('#<(foreignObject|iframe|embed|object)[^>]*>.*?</\1>#is', '', $content) ?? $content;
        $content = preg_replace('#(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*("|\')#i', '', $content) ?? $content;
        @file_put_contents($path, $content);
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is too large for this server. ' . self::serverLimitAdvice(),
            UPLOAD_ERR_PARTIAL                        => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE                        => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Server is missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE                     => 'Server could not write the file to disk.',
            UPLOAD_ERR_EXTENSION                      => 'A PHP extension blocked the upload.',
            default                                   => 'The file could not be uploaded.',
        };
    }
}
