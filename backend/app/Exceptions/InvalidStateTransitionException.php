<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Throwable;

class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        string $message = 'Invalid state transition.',
        mixed $errors = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, ErrorCode::INVALID_STATE_TRANSITION, $errors, 409, $previous);
    }
}
