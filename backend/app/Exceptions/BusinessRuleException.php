<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Throwable;

class BusinessRuleException extends DomainException
{
    public function __construct(
        string $message,
        ErrorCode|string $errorCode = ErrorCode::BUSINESS_RULE_VIOLATION,
        mixed $errors = [],
        int $httpStatus = 409,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $errors, $httpStatus, $previous);
    }
}
