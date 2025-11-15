<?php

use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Exceptions\VatVerificationException;

describe('ApiUnavailableException', function () {
    it('can be created with provider name and previous exception', function () {
        $previous = new \Exception('Original error');
        $exception = new ApiUnavailableException('VIES_REST', $previous);

        expect($exception)->toBeInstanceOf(ApiUnavailableException::class);
        expect($exception->getMessage())->toContain('VIES_REST');
        expect($exception->getPrevious())->toBe($previous);
    });

    it('contains provider name in message', function () {
        $exception = new ApiUnavailableException('TEST_PROVIDER');

        expect($exception->getMessage())->toContain('TEST_PROVIDER');
    });

    it('can retrieve API source', function () {
        $exception = new ApiUnavailableException('AEAT');

        expect($exception->getApiSource())->toBe('AEAT');
    });

    it('has 503 error code', function () {
        $exception = new ApiUnavailableException('TEST');

        expect($exception->getCode())->toBe(503);
    });

    it('has API_UNAVAILABLE error code', function () {
        $exception = new ApiUnavailableException('TEST');

        expect($exception->getErrorCode())->toBe('API_UNAVAILABLE');
    });
});

describe('VatVerificationException', function () {
    it('can be created with custom message', function () {
        $exception = new VatVerificationException('Custom error message');

        expect($exception)->toBeInstanceOf(VatVerificationException::class);
        expect($exception->getMessage())->toBe('Custom error message');
    });

    it('can be created with error code string', function () {
        $exception = new VatVerificationException('Error message', 'CUSTOM_ERROR');

        expect($exception->getErrorCode())->toBe('CUSTOM_ERROR');
    });

    it('can be created with API source', function () {
        $exception = new VatVerificationException('Error message', 'ERROR_CODE', 'TEST_API');

        expect($exception->getApiSource())->toBe('TEST_API');
    });

    it('can be created with previous exception', function () {
        $previous = new \Exception('Previous error');
        $exception = new VatVerificationException('Error occurred', 'ERROR', null, 0, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('can be created with numeric error code', function () {
        $exception = new VatVerificationException('Error', 'ERROR', null, 500);

        expect($exception->getCode())->toBe(500);
    });

    it('defaults to UNKNOWN error code when not provided', function () {
        $exception = new VatVerificationException('Error message');

        expect($exception->getErrorCode())->toBe('UNKNOWN');
    });
});
