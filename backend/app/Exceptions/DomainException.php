<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Exception;
use Throwable;

abstract class DomainException extends Exception
{
    public function __construct(
        string $message,
        protected ErrorCode|string $errorCode = ErrorCode::BUSINESS_RULE_VIOLATION,
        protected mixed $errors = [],
        int $httpStatus = 409,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode instanceof ErrorCode ? $this->errorCode->value : $this->errorCode;
    }

    public function getErrors(): mixed
    {
        return $this->errors;
    }

    public function getHttpStatus(): int
    {
        return $this->getCode();
    }
}
