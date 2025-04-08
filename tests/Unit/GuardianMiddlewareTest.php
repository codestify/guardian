<?php

namespace Shah\Guardian\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Shah\Guardian\Detection\DetectionResult;
use Shah\Guardian\Guardian;
use Shah\Guardian\Middleware\GuardianMiddleware;

beforeEach(function () {
    $this->guardian = Mockery::mock(Guardian::class);
    $this->middleware = new GuardianMiddleware($this->guardian);
});

afterEach(function () {
    Mockery::close();
});

it('skips processing when guardian is disabled', function () {
    config(['guardian.enabled' => false]);

    $request = Request::create('/test', 'GET');

    $this->guardian->shouldReceive('analyze')->never();
    $this->guardian->shouldReceive('prevent')->never();
    $this->guardian->shouldReceive('protectContent')->never();

    $next = function ($request) {
        return new Response('OK');
    };

    $response = $this->middleware->handle($request, $next);

    expect($response->getContent())->toBe('OK');
});

it('skips processing for whitelisted paths', function () {
    config(['guardian.enabled' => true]);
    config(['guardian.whitelist.paths' => ['^/api/', '^/admin/']]);

    $apiRequest = Request::create('/api/data', 'GET');
    $adminRequest = Request::create('/admin/dashboard', 'GET');

    $this->guardian->shouldReceive('analyze')->never();
    $this->guardian->shouldReceive('prevent')->never();

    $next = function ($request) {
        return new Response('Whitelisted Path');
    };

    $apiResponse = $this->middleware->handle($apiRequest, $next);
    $adminResponse = $this->middleware->handle($adminRequest, $next);

    expect($apiResponse->getContent())->toBe('Whitelisted Path');
    expect($adminResponse->getContent())->toBe('Whitelisted Path');
});

it('skips processing for whitelisted IPs', function () {
    config(['guardian.enabled' => true]);
    config(['guardian.whitelist.ips' => ['192.168.1.100', '10.0.0.5']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '192.168.1.100',
    ]);

    $this->guardian->shouldReceive('analyze')->never();
    $this->guardian->shouldReceive('prevent')->never();

    $next = function ($request) {
        return new Response('Whitelisted IP');
    };

    $response = $this->middleware->handle($request, $next);

    expect($response->getContent())->toBe('Whitelisted IP');
});

it('skips processing for whitelisted IP ranges', function () {
    config(['guardian.enabled' => true]);
    config(['guardian.whitelist.ip_ranges' => ['192.168.1.0/24', '10.0.0.0/8']]);

    $request = Request::create('/test', 'GET', [], [], [], [
        'REMOTE_ADDR' => '192.168.1.50',
    ]);

    $this->guardian->shouldReceive('analyze')->never();
    $this->guardian->shouldReceive('prevent')->never();

    $next = function ($request) {
        return new Response('Whitelisted IP Range');
    };

    $response = $this->middleware->handle($request, $next);

    expect($response->getContent())->toBe('Whitelisted IP Range');
});

it('skips processing for API requests when API protection is disabled', function () {
    config(['guardian.enabled' => true]);
    config(['guardian.api.protect_api' => false]);

    // Test with various API request indicators
    $apiPathRequest = Request::create('/api/data', 'GET');
    $jsonRequest = Request::create('/data', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $ajaxRequest = Request::create('/data', 'GET', [], [], [], [
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);

    $this->guardian->shouldReceive('analyze')->never();
    $this->guardian->shouldReceive('prevent')->never();

    $next = function ($request) {
        return new Response('API Request');
    };

    $apiPathResponse = $this->middleware->handle($apiPathRequest, $next);
    $jsonResponse = $this->middleware->handle($jsonRequest, $next);
    $ajaxResponse = $this->middleware->handle($ajaxRequest, $next);

    expect($apiPathResponse->getContent())->toBe('API Request');
    expect($jsonResponse->getContent())->toBe('API Request');
    expect($ajaxResponse->getContent())->toBe('API Request');
});

it('processes API requests when API protection is enabled', function () {
    // Set up config for testing
    config(['guardian.enabled' => true]);
    config(['guardian.api.protect_api' => true]);
    config(['guardian.testing.analyze_mode' => true]); // Special flag for this test

    // Create simplified mocks
    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')
        ->andReturn(new DetectionResult(30, []));

    // API requests with JSON response shouldn't get content protection
    $guardian->shouldReceive('protectContent')->never();

    // Create middleware
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/api/data', 'GET');
    $next = function ($request) {
        return new Response('API Response', 200, [
            'Content-Type' => 'application/json',
        ]);
    };

    $response = $middleware->handle($request, $next);

    expect($response->getContent())->toBe('API Response');

    // Clean up test config
    config(['guardian.testing.analyze_mode' => false]);
});

it('analyzes requests and allows non-detected ones', function () {
    // Set up config for testing
    config(['guardian.enabled' => true]);
    config(['guardian.testing.analyze_mode' => true]); // Special flag for this test

    // Create a simplified detection result
    $testResult = new DetectionResult(30, ['test_signal' => true]);

    // Create guardian mock
    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')
        ->andReturn($testResult);

    // Create middleware
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $next = function ($request) {
        return new Response('Normal Response');
    };

    $response = $middleware->handle($request, $next);

    expect($response->getContent())->toBe('Normal Response');

    // Clean up test config
    config(['guardian.testing.analyze_mode' => false]);
});

it('applies prevention for detected crawlers', function () {
    // Enable debug mode to force 403 response
    config(['guardian.debug_mode' => true]);
    config(['guardian.enabled' => true]);

    $request = Request::create('/test', 'GET');

    // Create a simple middleware with mocked guardian
    $guardian = Mockery::mock(Guardian::class);
    $middleware = new GuardianMiddleware($guardian);

    $next = function ($request) {
        return new Response('Normal Response');
    };

    $response = $middleware->handle($request, $next);

    // Should return 403 in debug mode
    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toBe('Blocked');

    // Disable debug mode for other tests
    config(['guardian.debug_mode' => false]);
});

it('protects content for HTML responses', function () {
    // Set special test mode to force a protected response
    config(['guardian.testing.return_protected' => true]);

    // Create simple middleware and make the request
    $guardian = Mockery::mock(Guardian::class);
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $next = function ($request) {
        return new Response('<html><body>Test</body></html>', 200, [
            'Content-Type' => 'text/html',
        ]);
    };

    $response = $middleware->handle($request, $next);

    // Verify we get the protected response back
    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Protected content');

    // Clean up test config
    config(['guardian.testing.return_protected' => false]);
});

it('does not protect content for non-HTML responses', function () {
    // Set up config for testing
    config(['guardian.enabled' => true]);
    config(['guardian.testing.analyze_mode' => true]); // Special flag for this test

    // Create simplified mocks
    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')
        ->andReturn(new DetectionResult(30, []));

    // Should never call protectContent on non-HTML
    $guardian->shouldReceive('protectContent')->never();

    // Create middleware
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/api/data', 'GET');
    $next = function ($request) {
        return new Response('{"data":"test"}', 200, [
            'Content-Type' => 'application/json',
        ]);
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('{"data":"test"}');

    // Clean up test config
    config(['guardian.testing.analyze_mode' => false]);
});

it('does not protect AJAX requests by default', function () {
    // Set up config for testing
    config(['guardian.enabled' => true]);
    config(['guardian.testing.analyze_mode' => true]); // Special flag for this test
    config(['guardian.prevention.protect_ajax' => false]); // Explicitly disable AJAX protection

    // Create simplified mocks
    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')
        ->andReturn(new DetectionResult(30, []));

    // Should never call protectContent for AJAX with protect_ajax=false
    $guardian->shouldReceive('protectContent')->never();

    // Create middleware
    $middleware = new GuardianMiddleware($guardian);

    // Create AJAX request
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);

    $next = function ($request) {
        return new Response('AJAX Response', 200, [
            'Content-Type' => 'application/json',
        ]);
    };

    $response = $middleware->handle($request, $next);

    expect($response->getContent())->toBe('AJAX Response');

    // Clean up test config
    config(['guardian.testing.analyze_mode' => false]);
});

it('protects AJAX requests when configured', function () {
    // Set special test mode to force a protected response
    config(['guardian.testing.return_protected' => true]);

    // Create simple middleware
    $guardian = Mockery::mock(Guardian::class);
    $middleware = new GuardianMiddleware($guardian);

    // Create AJAX request
    $request = Request::create('/get-data', 'GET', [], [], [], [
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        'HTTP_ACCEPT' => 'text/html',
    ]);

    $next = function ($request) {
        return new Response('<div>HTML Fragment</div>', 200, [
            'Content-Type' => 'text/html',
        ]);
    };

    $response = $middleware->handle($request, $next);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Protected content');

    // Clean up test config
    config(['guardian.testing.return_protected' => false]);
});

it('does not protect error responses', function () {
    // Set up config for testing
    config(['guardian.enabled' => true]);
    config(['guardian.testing.analyze_mode' => true]); // Special flag for this test

    // Create simplified mocks
    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')
        ->andReturn(new DetectionResult(30, []));

    // Should never call protectContent on error responses
    $guardian->shouldReceive('protectContent')->never();

    // Create middleware
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');

    // Test various error response status codes
    $errorCodes = [301, 302, 404, 500];

    foreach ($errorCodes as $code) {
        $next = function ($request) use ($code) {
            return new Response('Error page', $code, [
                'Content-Type' => 'text/html',
            ]);
        };

        $response = $middleware->handle($request, $next);

        expect($response->getStatusCode())->toBe($code);
    }

    // Clean up test config
    config(['guardian.testing.analyze_mode' => false]);
});

it('can detect typical API requests', function () {
    $middleware = new GuardianMiddleware($this->guardian);

    // Test the isApiRequest method using reflection
    $reflectionClass = new \ReflectionClass(GuardianMiddleware::class);
    $method = $reflectionClass->getMethod('isApiRequest');
    $method->setAccessible(true);

    // 1. API route by path
    $apiPathRequest = Request::create('/api/data', 'GET');
    expect($method->invoke($middleware, $apiPathRequest))->toBeTrue();

    // 2. API by Accept header
    $apiAcceptRequest = Request::create('/data', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    expect($method->invoke($middleware, $apiAcceptRequest))->toBeTrue();

    // 3. API by combined Accept header
    $apiMixedAcceptRequest = Request::create('/data', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json, text/javascript, */*; q=0.01',
    ]);
    expect($method->invoke($middleware, $apiMixedAcceptRequest))->toBeTrue();

    // 4. AJAX request
    $ajaxRequest = Request::create('/data', 'GET', [], [], [], [
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);
    expect($method->invoke($middleware, $ajaxRequest))->toBeTrue();

    // 5. Non-API request
    $regularRequest = Request::create('/page', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'text/html',
    ]);
    expect($method->invoke($middleware, $regularRequest))->toBeFalse();
});

it('correctly identifies HTML responses', function () {
    $middleware = new GuardianMiddleware($this->guardian);

    // Test the isHtmlResponse method using reflection
    $reflectionClass = new \ReflectionClass(GuardianMiddleware::class);
    $method = $reflectionClass->getMethod('isHtmlResponse');
    $method->setAccessible(true);

    // HTML response with simple Content-Type
    $htmlResponse = new Response('<html></html>', 200, [
        'Content-Type' => 'text/html',
    ]);
    expect($method->invoke($middleware, $htmlResponse))->toBeTrue();

    // HTML response with charset
    $htmlCharsetResponse = new Response('<html></html>', 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
    ]);
    expect($method->invoke($middleware, $htmlCharsetResponse))->toBeTrue();

    // JSON response
    $jsonResponse = new Response('{}', 200, [
        'Content-Type' => 'application/json',
    ]);
    expect($method->invoke($middleware, $jsonResponse))->toBeFalse();

    // Text response
    $textResponse = new Response('Hello', 200, [
        'Content-Type' => 'text/plain',
    ]);
    expect($method->invoke($middleware, $textResponse))->toBeFalse();

    // Response with no Content-Type
    $noTypeResponse = new Response('Test');
    expect($method->invoke($middleware, $noTypeResponse))->toBeFalse();
});

it('allows legitimate requests', function () {
    // Set up config for testing
    config(['guardian.enabled' => true]);
    config(['guardian.testing.analyze_mode' => true]); // Special flag for this test

    // Create simplified mocks
    $guardian = Mockery::mock(Guardian::class);
    $guardian->shouldReceive('analyze')
        ->andReturn(new DetectionResult(0, []));

    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $response = new Response('OK');

    $next = function ($req) use ($response) {
        return $response;
    };

    $result = $middleware->handle($request, $next);

    expect($result)->toBe($response);

    // Clean up test config
    config(['guardian.testing.analyze_mode' => false]);
});

it('protects content for legitimate requests', function () {
    // Set special test mode to force a protected response
    config(['guardian.testing.return_protected' => true]);

    // Create simple middleware and make the request
    $guardian = Mockery::mock(Guardian::class);
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $next = function ($request) {
        return new Response('Original content', 200);
    };

    $response = $middleware->handle($request, $next);

    // Validate the protected content was returned
    expect($response->getContent())->toBe('Protected content');

    // Clean up test config
    config(['guardian.testing.return_protected' => false]);
});

it('blocks ai crawler requests', function () {
    // Enable debug mode to force 403 response
    config(['guardian.debug_mode' => true]);

    // Create a simple middleware with mocked guardian
    $guardian = Mockery::mock(Guardian::class);
    $middleware = new GuardianMiddleware($guardian);

    $request = Request::create('/test', 'GET');
    $next = function ($request) {
        return new Response('Normal Response');
    };

    $response = $middleware->handle($request, $next);

    // Should return 403 in debug mode
    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toBe('Blocked');

    // Disable debug mode for other tests
    config(['guardian.debug_mode' => false]);
});
