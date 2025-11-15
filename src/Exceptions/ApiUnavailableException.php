<?php

namespace Aichadigital\Lararoi\Exceptions;

/**
 * Exception thrown when the API is not available
 */
class ApiUnavailableException extends VatVerificationException
{
    public function __construct(string $apiSource, ?\Throwable $previous = null)
    {
        $message = "API '{$apiSource}' is currently unavailable";
        parent::__construct($message, 'API_UNAVAILABLE', $apiSource, 503, $previous);
    }
}
