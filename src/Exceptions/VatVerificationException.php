<?php

namespace Aichadigital\Lararoi\Exceptions;

use Exception;

/**
 * Base exception for VAT verification errors
 */
class VatVerificationException extends Exception
{
    protected string $errorCode;

    protected ?string $apiSource = null;

    public function __construct(
        string $message = '',
        string $errorCode = 'UNKNOWN',
        ?string $apiSource = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->apiSource = $apiSource;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getApiSource(): ?string
    {
        return $this->apiSource;
    }
}
