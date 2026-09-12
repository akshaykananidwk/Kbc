<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(private array $errors, string $message = 'The submitted data is not valid.')
    {
        parent::__construct($message, 422);
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
