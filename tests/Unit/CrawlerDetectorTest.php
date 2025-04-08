<?php

namespace Shah\Guardian\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Shah\Guardian\Detection\Analyzers\BehaviouralAnalyzer;
use Shah\Guardian\Detection\Analyzers\HeaderAnalyzer;
use Shah\Guardian\Detection\Analyzers\RateLimitAnalyzer;
use Shah\Guardian\Detection\Analyzers\RequestPatternAnalyzer;
use Shah\Guardian\Detection\CrawlerDetector;
use Shah\Guardian\Detection\DetectionResult;

beforeEach(function () {
    // Flush the cache before each test
    Cache::flush();

    // Mock analyzer dependencies
    $this->headerAnalyzer = Mockery::mock(HeaderAnalyzer::class);
    $this->requestPatternAnalyzer = Mockery::mock(RequestPatternAnalyzer::class);
    $this->rateLimitAnalyzer = Mockery::mock(RateLimitAnalyzer::class);
    $this->behavioralAnalyzer = Mockery::mock(BehaviouralAnalyzer::class);

    // Create detector instance with mocked analyzers
    $this->detector = new CrawlerDetector(
        $this->headerAnalyzer,
        $this->requestPatternAnalyzer,
        $this->rateLimitAnalyzer,
        $this->behavioralAnalyzer
    );
});

afterEach(function () {
    Mockery::close();
});

it('skips detection if server detection is disabled', function () {
    config(['guardian.detection.server_enabled' => false]);

    $request = Request::create('/test', 'GET');
    $result = $this->detector->analyze($request);

    expect($result->score)->toBe(0)
        ->and($result->signals)->toBeEmpty();
});

it('detects known AI crawlers based on user agent', function () {
    // Set crawler signatures
    config(['guardian.detection.ai_crawler_signatures' => ['GPTBot', 'CCBot']]);

    // Test with GPTBot user agent
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)',
    ]);

    // Expect no analyzer calls since it should be detected as known crawler
    $this->headerAnalyzer->shouldReceive('analyze')->never();
    $this->requestPatternAnalyzer->shouldReceive('analyze')->never();
    $this->rateLimitAnalyzer->shouldReceive('analyze')->never();
    $this->behavioralAnalyzer->shouldReceive('analyze')->never();

    $result = $this->detector->analyze($request);

    expect($result->score)->toBe(100)
        ->and($result->signals)->toHaveKey('known_crawler')
        ->and($result->isDetected())->toBeTrue();
});

it('runs analyzers in sequence for non-crawler requests', function () {
    // Normal browser request
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124 Safari/537.36',
    ]);

    // Set up mock analyzer responses
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, ['header_signal' => true]));

    $this->requestPatternAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(15, ['pattern_signal' => true]));

    $this->rateLimitAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(5, ['rate_signal' => true]));

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(20, ['behavior_signal' => true]));

    $result = $this->detector->analyze($request);

    // Total score should be sum of all analyzer scores
    expect($result->score)->toBe(50)
        ->and($result->signals)->toHaveKey('header_signal')
        ->and($result->signals)->toHaveKey('pattern_signal')
        ->and($result->signals)->toHaveKey('rate_signal')
        ->and($result->signals)->toHaveKey('behavior_signal');
});

it('stops analysis early when high confidence detection occurs', function () {
    $request = Request::create('/test', 'GET');

    // First analyzer returns high score
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(85, ['critical_header_issue' => true]));

    // Other analyzers should not be called due to early return
    $this->requestPatternAnalyzer->shouldReceive('analyze')->never();
    $this->rateLimitAnalyzer->shouldReceive('analyze')->never();
    $this->behavioralAnalyzer->shouldReceive('analyze')->never();

    $result = $this->detector->analyze($request);

    expect($result->score)->toBe(85)
        ->and($result->signals)->toHaveKey('critical_header_issue');
});

it('skips disabled analyzers', function () {
    // Disable request pattern and rate limit analyzers
    config([
        'guardian.detection.analyzers.pattern' => false,
        'guardian.detection.analyzers.rate_limit' => false,
    ]);

    $request = Request::create('/test', 'GET');

    // Only enabled analyzers should be called
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, ['header_signal' => true]));

    $this->requestPatternAnalyzer->shouldReceive('analyze')->never();
    $this->rateLimitAnalyzer->shouldReceive('analyze')->never();

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(20, ['behavior_signal' => true]));

    $result = $this->detector->analyze($request);

    expect($result->score)->toBe(30)
        ->and($result->signals)->toHaveKey('header_signal')
        ->and($result->signals)->toHaveKey('behavior_signal')
        ->and($result->signals)->not->toHaveKey('pattern_signal')
        ->and($result->signals)->not->toHaveKey('rate_signal');
});

it('handles analyzer exceptions gracefully', function () {
    $request = Request::create('/test', 'GET');

    // One analyzer throws exception
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, ['header_signal' => true]));

    $this->requestPatternAnalyzer->shouldReceive('analyze')
        ->once()
        ->andThrow(new \Exception('Test exception'));

    $this->rateLimitAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(15, ['rate_signal' => true]));

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(20, ['behavior_signal' => true]));

    // Should continue processing despite exception
    $result = $this->detector->analyze($request);

    expect($result->score)->toBe(45)
        ->and($result->signals)->toHaveKey('header_signal')
        ->and($result->signals)->toHaveKey('rate_signal')
        ->and($result->signals)->toHaveKey('behavior_signal')
        ->and($result->signals)->not->toHaveKey('pattern_signal');
});

it('uses cached results when available', function () {
    // Create request with specific fingerprint
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Test Browser/1.0',
        'REMOTE_ADDR' => '192.168.1.1',
    ]);

    // First call should use analyzers
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, ['header_signal' => true]));

    $this->requestPatternAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(15, ['pattern_signal' => true]));

    $this->rateLimitAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(5, ['rate_signal' => true]));

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(20, ['behavior_signal' => true]));

    // First call
    $this->detector->analyze($request);

    // Second call with same request should use cache
    $this->headerAnalyzer->shouldReceive('analyze')->never();
    $this->requestPatternAnalyzer->shouldReceive('analyze')->never();
    $this->rateLimitAnalyzer->shouldReceive('analyze')->never();
    $this->behavioralAnalyzer->shouldReceive('analyze')->never();

    $result = $this->detector->analyze($request);

    expect($result->score)->toBe(50)
        ->and($result->signals)->toHaveKeys(['header_signal', 'pattern_signal', 'rate_signal', 'behavior_signal']);
});

it('handles requests with empty or missing user agent', function () {
    // Request with empty user agent
    $emptyUaRequest = Request::create('/test', 'GET');

    // Make expectations for analyzers
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(30, ['missing_user_agent' => true]));

    $this->requestPatternAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $this->rateLimitAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $result = $this->detector->analyze($emptyUaRequest);

    expect($result->score)->toBe(60)
        ->and($result->signals)->toHaveKey('missing_user_agent');
});

it('generates unique request fingerprints', function () {
    // Enable detailed fingerprinting
    config(['guardian.detection.detailed_fingerprinting' => true]);

    // Create two similar but distinct requests
    $request1 = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Test Browser/1.0',
        'HTTP_ACCEPT' => 'text/html',
        'REMOTE_ADDR' => '192.168.1.1',
    ]);

    $request2 = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Test Browser/1.0',
        'HTTP_ACCEPT' => 'application/json',
        'REMOTE_ADDR' => '192.168.1.1',
    ]);

    // Mock analyzers to return different results based on request
    $this->headerAnalyzer->shouldReceive('analyze')
        ->twice()
        ->andReturn(
            new DetectionResult(10, ['for_request1' => true]),
            new DetectionResult(20, ['for_request2' => true])
        );

    $this->requestPatternAnalyzer->shouldReceive('analyze')
        ->twice()
        ->andReturn(new DetectionResult(5, []));

    $this->rateLimitAnalyzer->shouldReceive('analyze')
        ->twice()
        ->andReturn(new DetectionResult(5, []));

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->twice()
        ->andReturn(new DetectionResult(5, []));

    // Analyze both requests
    $result1 = $this->detector->analyze($request1);
    $result2 = $this->detector->analyze($request2);

    // Results should be different due to different fingerprints
    expect($result1->signals)->toHaveKey('for_request1')
        ->and($result1->signals)->not->toHaveKey('for_request2')
        ->and($result2->signals)->toHaveKey('for_request2')
        ->and($result2->signals)->not->toHaveKey('for_request1');
});

it('allows adding custom analyzers', function () {
    $request = Request::create('/test', 'GET');

    // Create custom analyzer
    $customAnalyzer = Mockery::mock();
    $customAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(25, ['custom_signal' => true]));

    // Set expectations for built-in analyzers
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, ['header_signal' => true]));

    $this->requestPatternAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(15, ['pattern_signal' => true]));

    $this->rateLimitAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(5, ['rate_signal' => true]));

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, ['behavior_signal' => true]));

    // Add custom analyzer
    $this->detector->addAnalyzer($customAnalyzer, 'custom');

    $result = $this->detector->analyze($request);

    // Result should include signals from custom analyzer
    expect($result->score)->toBe(65)
        ->and($result->signals)->toHaveKey('custom_signal');
});

it('handles extremely long user agents', function () {
    // Generate a very long user agent (over 2000 chars)
    $longUserAgent = str_repeat('Mozilla/5.0 (Windows NT 10.0; Win64; x64) ', 100);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $longUserAgent,
    ]);

    // Mock analyzer responses
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(30, ['suspicious_ua_length' => true]));

    $this->requestPatternAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $this->rateLimitAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $result = $this->detector->analyze($request);

    expect($result->score)->toBe(60)
        ->and($result->signals)->toHaveKey('suspicious_ua_length');
});

it('handles malformed headers', function () {
    // Create request with malformed headers
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => "Mozilla/5.0\0(invalid)",
        'HTTP_ACCEPT' => "text/html,\r\napplication/xhtml+xml",
    ]);

    // Mock analyzer responses
    $this->headerAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(40, ['malformed_headers' => true]));

    $this->requestPatternAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $this->rateLimitAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $this->behavioralAnalyzer->shouldReceive('analyze')
        ->once()
        ->andReturn(new DetectionResult(10, []));

    $result = $this->detector->analyze($request);

    expect($result->score)->toBe(70)
        ->and($result->signals)->toHaveKey('malformed_headers');
});
