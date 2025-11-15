<?php

use Aichadigital\Lararoi\Exceptions\ApiUnavailableException;
use Aichadigital\Lararoi\Providers\IsvatProvider;
use Aichadigital\Lararoi\Providers\VatlayerProvider;
use Aichadigital\Lararoi\Providers\ViesApiProvider;
use Aichadigital\Lararoi\Providers\ViesRestProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

describe('ViesRestProvider - Verify Method', function () {
    it('handles successful API response', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'isValid' => true,
                'name' => 'Test Company',
                'address' => 'Test Address',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
                'requestDate' => '2024-01-01',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new ViesRestProvider($client);
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
        expect($result['name'])->toBe('Test Company');
        expect($result['api_source'])->toBe('VIES_REST');
    });

    it('handles invalid VAT response', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'isValid' => false,
                'vatNumber' => 'B99999999',
                'countryCode' => 'ES',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new ViesRestProvider($client);
        $result = $provider->verify('B99999999', 'ES');

        expect($result['valid'])->toBeFalse();
    });

    it('throws exception when API is unavailable', function () {
        $mock = new MockHandler([
            new ConnectException('Connection failed', new Request('GET', 'test')),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new ViesRestProvider($client);

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception when response format is invalid', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'format'])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new ViesRestProvider($client);

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('IsvatProvider - Verify Method', function () {
    it('handles successful API response', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'valid' => true,
                'name' => 'Test Company',
                'address' => 'Test Address',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new IsvatProvider($client, false);
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
        expect($result['api_source'])->toBe('ISVAT');
    });

    it('uses live endpoint when configured', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'valid' => true,
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new IsvatProvider($client, true);
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
    });

    it('handles missing optional fields', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'valid' => false,
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new IsvatProvider($client);
        $result = $provider->verify('B99999999', 'ES');

        expect($result['valid'])->toBeFalse();
        expect($result['name'])->toBeNull();
        expect($result['request_date'])->toBeNull();
    });

    it('throws exception on API error', function () {
        $mock = new MockHandler([
            new ConnectException('Connection timeout', new Request('GET', 'test')),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new IsvatProvider($client);

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('VatlayerProvider - Verify Method', function () {
    it('handles successful API response', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'valid' => true,
                'company_name' => 'Test Company',
                'company_address' => 'Test Address',
                'vat_number' => 'ESB12345678',
                'country_code' => 'ES',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new VatlayerProvider($client, 'test_key');
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
        expect($result['api_source'])->toBe('VATLAYER');
    });

    it('handles API error response', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'error' => ['info' => 'Invalid API key'],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new VatlayerProvider($client, 'invalid_key');

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });

    it('throws exception when not available', function () {
        $provider = new VatlayerProvider(null, null);

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});

describe('ViesApiProvider - Verify Method', function () {
    it('handles successful API response', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'valid' => true,
                'name' => 'Test Company',
                'address' => 'Test Address',
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new ViesApiProvider($client, 'test_key');
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
        expect($result['api_source'])->toBe('VIESAPI');
    });

    it('includes IP headers when configured', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'valid' => true,
                'vatNumber' => 'B12345678',
                'countryCode' => 'ES',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new ViesApiProvider($client, 'test_key', null, '127.0.0.1');
        $result = $provider->verify('B12345678', 'ES');

        expect($result['valid'])->toBeTrue();
    });

    it('throws exception when not available', function () {
        $provider = new ViesApiProvider(null, null);

        expect(fn () => $provider->verify('B12345678', 'ES'))
            ->toThrow(ApiUnavailableException::class);
    });
});
