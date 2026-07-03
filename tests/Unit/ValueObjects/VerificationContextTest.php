<?php

use Aichadigital\Lararoi\ValueObjects\VerificationContext;

describe('VerificationContext (ADR-002 D4)', function () {
    it('carries the consumer and an opaque subject reference', function () {
        $context = new VerificationContext('larabill', 'invoice-42');

        expect($context->consumer)->toBe('larabill')
            ->and($context->subjectReference)->toBe('invoice-42');
    });

    it('defaults the subject reference to null', function () {
        $context = new VerificationContext('larabill');

        expect($context->consumer)->toBe('larabill')
            ->and($context->subjectReference)->toBeNull();
    });

    it('is an immutable readonly value object', function () {
        $context = new VerificationContext('larabill', 'ref');

        // Attempting to mutate a readonly promoted property is an Error.
        expect(fn () => $context->consumer = 'other')->toThrow(Error::class);
    });
});
