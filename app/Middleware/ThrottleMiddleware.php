<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;

/**
 * Lightweight file backed rate limiter used for endpoints that must work
 * before the database connection exists (the installer, the login form).
 */
final class ThrottleMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        [$limit, $seconds] = array_pad(explode(',', (string) ($this->argument ?? '30,60')), 2, '60');
        $limit = max(1, (int) $limit);
        $seconds = max(1, (int) $seconds);

        $key = sha1($request->ip() . '|' . $request->path());
        $dir = Application::instance()->storagePath('cache/throttle');
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . $key . '.json';

        $now = time();
        $entry = ['count' => 0, 'reset' => $now + $seconds];
        if (is_file($file)) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            if (is_array($decoded) && (int) ($decoded['reset'] ?? 0) > $now) {
                $entry = ['count' => (int) $decoded['count'], 'reset' => (int) $decoded['reset']];
            }
        }

        $entry['count']++;
        @file_put_contents($file, json_encode($entry), LOCK_EX);

        if ($entry['count'] > $limit) {
            $retry = max(1, $entry['reset'] - $now);
            if ($request->expectsJson()) {
                return Response::apiError('Too many requests. Try again in ' . $retry . ' seconds.', 429)
                    ->withHeader('Retry-After', (string) $retry);
            }
            return Response::html(
                '<h1>Too many requests</h1><p>Please wait ' . $retry . ' seconds and try again.</p>',
                429
            )->withHeader('Retry-After', (string) $retry);
        }

        return null;
    }
}
