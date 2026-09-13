<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    /**
     * @param array<string,mixed> $context Extra detail the client can act on,
     *                                     such as which game is blocking a new one.
     */
    public function __construct(
        private int $statusCode,
        string $message = '',
        private array $context = []
    ) {
        parent::__construct($message === '' ? self::defaultMessage($statusCode) : $message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->context;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400     => 'Bad request.',
            401     => 'Authentication required.',
            403     => 'You are not allowed to access this page.',
            404     => 'Page not found.',
            405     => 'Method not allowed.',
            419     => 'Your session has expired. Please try again.',
            429     => 'Too many requests. Please slow down.',
            500     => 'Something went wrong on our side.',
            default => 'Request could not be completed.',
        };
    }
}
