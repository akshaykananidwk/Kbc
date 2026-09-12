<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

abstract class Middleware
{
    public function __construct(protected ?string $argument = null)
    {
    }

    /** Returning a Response short-circuits the pipeline. */
    abstract public function handle(Request $request): ?Response;

    public function terminate(Request $request, Response $response): Response
    {
        return $response;
    }
}
