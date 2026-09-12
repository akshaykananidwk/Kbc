<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/** Keeps admin/operator/display screens out of search engines. */
final class NoIndexMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        return null;
    }

    public function terminate(Request $request, Response $response): Response
    {
        return $response->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
    }
}
