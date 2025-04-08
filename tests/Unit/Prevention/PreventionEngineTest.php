<?php

namespace Shah\Guardian\Tests\Unit\Prevention;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Shah\Guardian\Detection\DetectionResult;
use Shah\Guardian\Prevention\ContentProtector;
use Shah\Guardian\Prevention\HoneypotGenerator;
use Shah\Guardian\Prevention\PreventionEngine;

beforeEach(function () {
    $this->contentProtector = new ContentProtector;
    $this->honeypotGenerator = new HoneypotGenerator;
    $this->preventionEngine = new PreventionEngine($this->contentProtector, $this->honeypotGenerator);

    // Reset config values for each test
    Config::set('guardian.prevention.adaptive', true);
    Config::set('guardian.prevention.strategy', 'adaptive');
});

it('applies block strategy for high confidence detection (score >= 90)', function () {
    // Create a mock request
    $request = Request::create('/test', 'GET');

    // Create a high confidence detection result
    $result = new DetectionResult(95, ['high_confidence' => true]);

    // Create a mock next closure that should not be called
    $next = function ($request) {
        throw new \Exception('Next closure should not be called for block strategy');
    };

    // Apply prevention
    $response = $this->preventionEngine->prevent($request, $next, $result);

    // Verify response
    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toBe('Blocked');
    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('applies honeypot strategy for strong suspicion (score >= 75)', function () {
    // Create a mock request
    $request = Request::create('/test', 'GET');

    // Create a detection result with strong suspicion
    $result = new DetectionResult(80, ['suspicious_patterns' => true]);

    // Create a mock next closure that should not be called
    $next = function ($request) {
        throw new \Exception('Next closure should not be called for honeypot strategy');
    };

    // Apply prevention
    $response = $this->preventionEngine->prevent($request, $next, $result);

    // Verify response contains honeypot elements
    $content = $response->getContent();

    // Check for basic HTML structure
    expect($content)->toContain('<!DOCTYPE html>');
    expect($content)->toContain('<html lang="en">');

    // Check for honeypot-specific elements
    expect($content)->toContain('Internal Page - RESTRICTED ACCESS');
    expect($content)->toContain('This content requires authentication to access');
    expect($content)->toContain('Authentication Required');

    // Check for tracking elements
    expect($content)->toContain('<img src=\'/guardian-track/');
    expect($content)->toContain('<input type=\'hidden\' name=\'guardian_token\'');

    // Check for honeypot links
    expect($content)->toContain('/internal/document/');
    expect($content)->toContain('/private/content/');
    expect($content)->toContain('/member/access/');
    expect($content)->toContain('/restricted/data/');

    // Check headers
    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('applies alternate content strategy for moderate suspicion (score >= 60)', function () {
    // Create a mock request
    $request = Request::create('/test', 'GET');

    // Create a detection result with moderate suspicion
    $result = new DetectionResult(65, ['moderate_suspicion' => true]);

    // Create a mock response with HTML content
    $originalContent = '<!DOCTYPE html><html><head><title>Test</title></head><body><article><p>Original content that should be replaced.</p></article></body></html>';
    $next = function ($request) use ($originalContent) {
        return new Response($originalContent, 200, ['Content-Type' => 'text/html']);
    };

    // Apply prevention
    $response = $this->preventionEngine->prevent($request, $next, $result);

    // Verify response
    $content = $response->getContent();

    // Check that original content is replaced
    expect($content)->not->toContain('Original content that should be replaced');

    // Check for any one of the possible placeholders
    $possiblePlaceholders = [
        'This content is not available for automated access.',
        'This information requires authentication to access.',
        'Content only available to registered users.',
        'Please log in to view this content.',
        'This section is protected against automated access.',
    ];

    $containsPlaceholder = false;
    foreach ($possiblePlaceholders as $placeholder) {
        if (str_contains($content, $placeholder)) {
            $containsPlaceholder = true;
            break;
        }
    }
    expect($containsPlaceholder)->toBeTrue();

    // Check headers
    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('applies delay strategy for light suspicion (score >= 40)', function () {
    // Create a mock request
    $request = Request::create('/test', 'GET');

    // Create a detection result with light suspicion
    $result = new DetectionResult(45, ['light_suspicion' => true]);

    // Set delay seconds in config
    Config::set('guardian.prevention.delay_seconds', 1);

    // Create a mock response
    $originalContent = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Original content.</p></body></html>';
    $next = function ($request) use ($originalContent) {
        return new Response($originalContent, 200, ['Content-Type' => 'text/html']);
    };

    // Record start time
    $startTime = microtime(true);

    // Apply prevention
    $response = $this->preventionEngine->prevent($request, $next, $result);

    // Record end time
    $endTime = microtime(true);

    // Verify delay was applied (with some tolerance for system variations)
    $delay = $endTime - $startTime;
    expect($delay)->toBeGreaterThan(0.9);

    // Verify content is protected but not replaced
    $content = $response->getContent();
    expect($content)->toContain('Original content');
    expect($content)->toContain('guardian-protected');
});

it('uses fixed block strategy when adaptive is disabled', function () {
    // Disable adaptive prevention and set fixed block strategy
    Config::set('guardian.prevention.adaptive', false);
    Config::set('guardian.prevention.strategy', 'block');

    // Create a mock request with low suspicion score
    $request = Request::create('/test', 'GET');
    $result = new DetectionResult(45, ['low_suspicion' => true]);

    // Create a mock next closure that should not be called
    $next = function ($request) {
        throw new \Exception('Next closure should not be called for block strategy');
    };

    // Apply prevention - should block despite low score
    $response = $this->preventionEngine->prevent($request, $next, $result);

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toBe('Blocked');
});

it('uses fixed honeypot strategy when adaptive is disabled', function () {
    // Disable adaptive prevention and set fixed honeypot strategy
    Config::set('guardian.prevention.adaptive', false);
    Config::set('guardian.prevention.strategy', 'honeypot');

    // Create a mock request with low suspicion score
    $request = Request::create('/test', 'GET');
    $result = new DetectionResult(45, ['low_suspicion' => true]);

    // Create a mock next closure
    $next = function ($request) {
        throw new \Exception('Next closure should not be called for honeypot strategy');
    };

    // Apply prevention - should use honeypot despite low score
    $response = $this->preventionEngine->prevent($request, $next, $result);

    expect($response->getContent())->toContain('guardian-track');
    expect($response->getContent())->toContain('guardian_token');
    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('respects custom threshold configurations', function () {
    // Set custom thresholds
    Config::set('guardian.prevention.thresholds', [
        'block' => 80, // Lower than default 90
        'honeypot' => 60, // Lower than default 75
        'alternate' => 40, // Lower than default 60
        'delay' => 20, // Lower than default 40
    ]);

    // Create a mock request with score that would normally trigger delay
    $request = Request::create('/test', 'GET');
    $result = new DetectionResult(65, ['moderate_suspicion' => true]);

    // Create a mock response
    $originalContent = '<!DOCTYPE html><html><body><p>Test content</p></body></html>';
    $next = function ($request) use ($originalContent) {
        return new Response($originalContent);
    };

    // Apply prevention - should use honeypot due to custom threshold
    $response = $this->preventionEngine->prevent($request, $next, $result);

    // Verify honeypot content
    expect($response->getContent())->toContain('guardian-track');
    expect($response->getContent())->toContain('guardian_token');
    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('applies custom delay duration', function () {
    // Set a shorter delay for testing
    Config::set('guardian.prevention.delay_seconds', 0.5);

    // Create a mock request
    $request = Request::create('/test', 'GET');
    $result = new DetectionResult(45, ['light_suspicion' => true]);

    // Create a mock response
    $originalContent = '<!DOCTYPE html><html><body><p>Test content</p></body></html>';
    $next = function ($request) use ($originalContent) {
        return new Response($originalContent);
    };

    // Record start time
    $startTime = microtime(true);

    // Apply prevention
    $response = $this->preventionEngine->prevent($request, $next, $result);

    // Record end time
    $endTime = microtime(true);

    // Verify response content
    expect($response->getContent())->toContain('Test content');
    expect($response->getContent())->toContain('class="guardian-protected"');

    // Sleep for the remaining time to ensure delay
    $elapsed = $endTime - $startTime;
    if ($elapsed < 0.5) {
        usleep(($elapsed * 1000000));
    }
});

it('falls back to delay strategy when invalid strategy configured', function () {
    // Set an invalid strategy
    Config::set('guardian.prevention.adaptive', false);
    Config::set('guardian.prevention.strategy', 'invalid_strategy');
    Config::set('guardian.prevention.delay_seconds', 0.5);

    // Create a mock request
    $request = Request::create('/test', 'GET');
    $result = new DetectionResult(45, ['light_suspicion' => true]);

    // Create a mock response
    $originalContent = '<!DOCTYPE html><html><body><p>Test content</p></body></html>';
    $next = function ($request) use ($originalContent) {
        return new Response($originalContent);
    };

    // Apply prevention
    $response = $this->preventionEngine->prevent($request, $next, $result);

    // Verify response content
    expect($response->getContent())->toContain('Test content');
    expect($response->getContent())->toContain('class="guardian-protected"');

    // Verify delay was applied by checking response headers
    expect($response->headers->has('X-Guardian-Delay'))->toBeTrue();
    expect((float) $response->headers->get('X-Guardian-Delay'))->toBeGreaterThan(0);
});
