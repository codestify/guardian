<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shah\Guardian\Detection\DetectionResult;
use Shah\Guardian\Guardian;
use Shah\Guardian\Middleware\GuardianMiddleware;

it('allows legitimate requests to pass through', function () {
    // Set up config for testing
    config(['guardian.testing.analyze_mode' => true]); // Special flag for this test

    // Create mock Guardian
    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')
        ->andReturn(new DetectionResult(30, []));

    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $next = function ($request) {
        return new Response('OK');
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('OK');

    // Clean up test config
    config(['guardian.testing.analyze_mode' => false]);
});

it('prevents detected crawlers based on configured strategy', function () {
    config(['guardian.debug_mode' => true]); // Use debug mode to force blocking

    // Create a simple middleware with mocked Guardian
    $guardian = Mockery::mock(Guardian::class);
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $next = function ($request) {
        return new Response('OK');
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toBe('Blocked');

    // Disable debug mode for other tests
    config(['guardian.debug_mode' => false]);
});

it('protects content for legitimate requests', function () {
    // Set special test mode to force a protected response
    config(['guardian.testing.return_protected' => true]);

    // Create simple middleware and request
    $guardian = Mockery::mock(Guardian::class);
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $next = function ($request) {
        return new Response('<html><body>Test Content</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Protected content');

    // Clean up test config
    config(['guardian.testing.return_protected' => false]);
});

it('respects whitelisted paths', function () {
    config(['guardian.whitelist.paths' => ['^/api/']]);

    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')->never();
    $guardian->shouldReceive('prevent')->never();

    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/api/users', 'GET');
    $next = function ($request) {
        return new Response('API Response');
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('API Response');
});

it('respects whitelisted IPs', function () {
    config(['guardian.whitelist.ips' => ['127.0.0.1']]);

    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')->never();
    $guardian->shouldReceive('prevent')->never();

    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $next = function ($request) {
        return new Response('Whitelisted IP');
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('Whitelisted IP');
});

it('respects whitelisted IP ranges', function () {
    config(['guardian.whitelist.ip_ranges' => ['192.168.1.0/24']]);

    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')->never();
    $guardian->shouldReceive('prevent')->never();

    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.100');

    $next = function ($request) {
        return new Response('Whitelisted IP Range');
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('Whitelisted IP Range');
});

it('respects disabled guardian', function () {
    config(['guardian.enabled' => false]);

    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')->never();
    $guardian->shouldReceive('prevent')->never();

    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $next = function ($request) {
        return new Response('Guardian Disabled');
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('Guardian Disabled');
});
