<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Throwable;

class ResourceConflictException extends DomainException
{
    public function __construct(
        string $message = 'Resource conflict.',
        ErrorCode|string $errorCode = ErrorCode::CONFLICT,
        mixed $errors = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $errors, 409, $previous);
    }
}
