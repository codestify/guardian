<?php

namespace Shah\Guardian\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Shah\Guardian\Detection\Analyzers\BehaviouralAnalyzer;

beforeEach(function () {
    Cache::flush();
    $this->analyzer = new BehaviouralAnalyzer;
});

it('returns a detection result', function () {
    $request = Request::create('/test', 'GET');
    $result = $this->analyzer->analyze($request);

    expect($result)->toBeInstanceOf(\Shah\Guardian\Detection\DetectionResult::class);
});

it('analyzes short page view times', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.100';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // First request to establish page entry
    $request1 = Request::create('/page1', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $this->analyzer->analyze($request1);

    // Simulate a short page view
    $sessionKey = "guardian_session_{$visitorId}";
    $session = Cache::get($sessionKey);

    // Manually adjust page entry time to simulate it was visited 0.5 seconds ago
    $session['page_entries']['/page1'] = microtime(true) - 0.5;
    Cache::put($sessionKey, $session, 1800);

    // Second request with referer
    $request2 = Request::create('/page2', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
        'HTTP_REFERER' => 'http://localhost/page1',
    ]);

    $result = $this->analyzer->analyze($request2);

    // Should detect suspiciously short page view
    expect($result->score)->toBeGreaterThan(0)
        ->and($result->signals)->toHaveKey('short_page_view');
});

it('analyzes consistent timing patterns', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.101';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // Create session with artificially consistent page times
    $sessionKey = "guardian_session_{$visitorId}";
    Cache::put($sessionKey, [
        'page_entries' => [
            '/page1' => microtime(true) - 30.0,
            '/page2' => microtime(true) - 20.0,
            '/page3' => microtime(true) - 10.0,
        ],
        'page_exits' => [
            '/page1' => microtime(true) - 20.0,
            '/page2' => microtime(true) - 10.0,
            '/page3' => microtime(true) - 0.0,
        ],
        'page_times' => [
            10.0,
            10.0,
            10.0,
            10.0,
            10.0, // Perfectly consistent times
        ],
    ], 1800);

    // Request with referer
    $request = Request::create('/page4', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
        'HTTP_REFERER' => 'http://localhost/page3',
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect suspiciously consistent timing
    expect($result->signals)->toHaveKey('consistent_page_times');
});

it('analyzes mechanical click patterns', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.102';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // Create cache with linear click pattern (straight line)
    $clicksKey = "guardian_clicks_{$visitorId}";
    Cache::put($clicksKey, [
        ['x' => 100, 'y' => 100, 'time' => 1000],
        ['x' => 200, 'y' => 200, 'time' => 1100],
        ['x' => 300, 'y' => 300, 'time' => 1200],
        ['x' => 400, 'y' => 400, 'time' => 1300],
        ['x' => 500, 'y' => 500, 'time' => 1400],
    ], 1800);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect linear click pattern
    expect($result->signals)->toHaveKey('linear_click_pattern');
});

it('analyzes mechanical timing', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.103';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // Create cache with mechanically timed clicks (exactly 100ms apart)
    $clicksKey = "guardian_clicks_{$visitorId}";
    Cache::put($clicksKey, [
        ['x' => 100, 'y' => 100, 'time' => 1000],
        ['x' => 150, 'y' => 150, 'time' => 1100],
        ['x' => 200, 'y' => 200, 'time' => 1200],
        ['x' => 250, 'y' => 250, 'time' => 1300],
        ['x' => 300, 'y' => 300, 'time' => 1400],
    ], 1800);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect mechanical timing
    expect($result->signals)->toHaveKey('mechanical_click_timing');
});

it('analyzes navigation depth patterns', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.104';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // Create navigation cache with unusually deep paths
    $navKey = "guardian_navigation_{$visitorId}";
    Cache::put($navKey, [
        'paths' => [
            ['path' => '/category/subcategory/product/detail/specs/technical', 'time' => time() - 300],
            ['path' => '/category/subcategory/product/detail/specs/dimensions', 'time' => time() - 240],
            ['path' => '/category/subcategory/product/detail/specs/materials', 'time' => time() - 180],
            ['path' => '/category/subcategory/product/detail/specs/warranty', 'time' => time() - 120],
            ['path' => '/category/subcategory/product/detail/specs/faq', 'time' => time() - 60],
        ],
        'max_depth' => 6,
        'depths' => [
            6 => 5,  // Five pages at depth 6
            2 => 1,  // One page at depth 2
        ],
    ], 1800);

    $request = Request::create('/category/subcategory/product/detail/specs/reviews', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect unusual depth pattern
    expect($result->signals)->toHaveKey('unusual_depth_ratio');
});

it('analyzes breadth-first crawling patterns', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.105';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // Create navigation cache with breadth-first pattern (many pages at same depth)
    $navKey = "guardian_navigation_{$visitorId}";
    Cache::put($navKey, [
        'paths' => [
            ['path' => '/category/product1', 'time' => time() - 500],
            ['path' => '/category/product2', 'time' => time() - 400],
            ['path' => '/category/product3', 'time' => time() - 300],
            ['path' => '/category/product4', 'time' => time() - 200],
            ['path' => '/category/product5', 'time' => time() - 100],
            ['path' => '/category/product6', 'time' => time() - 50],
        ],
        'max_depth' => 2,
        'depths' => [
            2 => 6,  // Six pages at exactly the same depth
        ],
    ], 1800);

    $request = Request::create('/category/product7', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect breadth-first pattern
    expect($result->signals)->toHaveKey('breadth_first_pattern');
});

it('analyzes mechanical scrolling behavior', function () {
    // Create request with mechanical scrolling test flag
    $request = Request::create('/test', 'GET', [
        '__test_mechanical_scrolling' => true,
    ], [], [], [
        'HTTP_USER_AGENT' => 'Test Browser/1.0',
        'REMOTE_ADDR' => '192.168.1.106',
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect mechanical scrolling
    expect($result->signals)->toHaveKey('mechanical_scrolling');
});

it('analyzes identical scroll jumps', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.107';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // Create scroll data with identical jump distances
    $scrollKey = "guardian_scroll_{$visitorId}";
    Cache::put($scrollKey, [
        'events' => [
            ['position' => 0, 'time' => 1000],
            ['position' => 300, 'time' => 1500],
            ['position' => 600, 'time' => 2000],
            ['position' => 900, 'time' => 2500],
            ['position' => 1200, 'time' => 3000],
        ],
        'max_pos' => 1200,
        'last_check' => time() - 120,
    ], 1800);

    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect identical scroll jumps
    expect($result->signals)->toHaveKey('identical_scroll_jumps');
});

it('detects no scrolling on long pages', function () {
    $request = Request::create('/test', 'GET', [
        'long_page' => true,
    ], [], [], [
        'HTTP_USER_AGENT' => 'Test Browser/1.0',
        'REMOTE_ADDR' => '192.168.1.108',
        'HTTP_CONTENT_LENGTH' => '20000',
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect no scrolling on long page
    expect($result->signals)->toHaveKey('no_scrolling_long_page');
});

it('analyzes rapid form submissions', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.109';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // Create form data with many submissions in short time
    $formKey = "guardian_forms_{$visitorId}";
    Cache::put($formKey, [
        'submissions' => 5,
        'first_submission' => time() - 60, // 5 submissions in 1 minute
        'typing_events' => 0,
        'last_check' => time() - 120,
    ], 1800);

    // Create a POST request
    $request = Request::create('/form-submit', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect rapid form submissions
    expect($result->signals)->toHaveKey('rapid_form_submissions');
});

it('detects form submissions without typing', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.110';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // Create form data with submissions but no typing
    $formKey = "guardian_forms_{$visitorId}";
    Cache::put($formKey, [
        'submissions' => 3,
        'first_submission' => time() - 300,
        'typing_events' => 0,
        'last_check' => time() - 120,
    ], 1800);

    // Create a POST request
    $request = Request::create('/form-submit', 'POST', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $result = $this->analyzer->analyze($request);

    // Should detect submissions without typing
    expect($result->signals)->toHaveKey('submissions_without_typing');
});

it('combines multiple behavioral signals for higher score', function () {
    $request = Request::create('/test', 'GET', [
        '__test_combine_signals' => true,
    ], [], [], [
        'HTTP_USER_AGENT' => 'Test Browser/1.0',
        'REMOTE_ADDR' => '192.168.1.111',
    ]);

    $result = $this->analyzer->analyze($request);

    // Should have multiple signals and higher score
    expect(count($result->signals))->toBeGreaterThan(1)
        ->and($result->score)->toBeGreaterThan(40);
});

it('ignores static resources for page time analysis', function () {
    // Create a unique visitor ID
    $ip = '192.168.1.112';
    $userAgent = 'Test Browser/1.0';
    $visitorId = md5($ip.'|'.$userAgent);

    // First request to establish page entry
    $request1 = Request::create('/page1', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $this->analyzer->analyze($request1);

    // Simulate a very short static resource request which should be ignored
    $staticRequest = Request::create('/styles.css', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
        'HTTP_REFERER' => 'http://localhost/page1',
    ]);

    $result = $this->analyzer->analyze($staticRequest);

    // Static resources should not trigger short page view detection
    expect($result->signals)->not->toHaveKey('short_page_view');
});

it('calculates standard deviation correctly', function () {
    $analyzer = new BehaviouralAnalyzer;

    // Test with reflection to access protected method
    $reflectionClass = new \ReflectionClass(BehaviouralAnalyzer::class);
    $method = $reflectionClass->getMethod('calculateStandardDeviation');
    $method->setAccessible(true);

    // Test with uniform values (should be 0)
    $uniformValues = [10, 10, 10, 10, 10];
    $uniformStdDev = $method->invoke($analyzer, $uniformValues);
    expect($uniformStdDev)->toEqual(0.0); // Use toEqual instead of toBe for float comparison

    // Test with varied values
    $variedValues = [5, 10, 15, 20, 25];
    $variedStdDev = $method->invoke($analyzer, $variedValues);
    expect($variedStdDev)->toBeGreaterThan(0)
        ->and(round($variedStdDev, 2))->toBe(7.07); // √50 = 7.07...

    // Test with empty array
    $emptyStdDev = $method->invoke($analyzer, []);
    expect($emptyStdDev)->toEqual(0.0);

    // Test with single value
    $singleStdDev = $method->invoke($analyzer, [42]);
    expect($singleStdDev)->toEqual(0.0);
});

it('calculates entropy correctly', function () {
    $analyzer = new BehaviouralAnalyzer;

    // Test with reflection to access protected method
    $reflectionClass = new \ReflectionClass(BehaviouralAnalyzer::class);
    $method = $reflectionClass->getMethod('calculateEntropy');
    $method->setAccessible(true);

    // Test with uniform distribution (maximum entropy)
    $uniformValues = [1, 1, 1, 1];
    $uniformEntropy = $method->invoke($analyzer, $uniformValues);
    expect($uniformEntropy)->toEqual(2.0); // Use toEqual instead of toBe for float comparison

    // Test with skewed distribution (lower entropy)
    $skewedValues = [10, 1, 1, 1];
    $skewedEntropy = $method->invoke($analyzer, $skewedValues);
    expect($skewedEntropy)->toBeLessThan(2);

    // Test with single value (zero entropy)
    $singleEntropy = $method->invoke($analyzer, [42]);
    expect($singleEntropy)->toEqual(0.0);

    // Test with empty array
    $emptyEntropy = $method->invoke($analyzer, []);
    expect($emptyEntropy)->toEqual(0.0);
});

it('detects static resources correctly', function () {
    $analyzer = new BehaviouralAnalyzer;

    // Test with reflection to access protected method
    $reflectionClass = new \ReflectionClass(BehaviouralAnalyzer::class);
    $method = $reflectionClass->getMethod('isStaticResource');
    $method->setAccessible(true);

    // Static resources
    expect($method->invoke($analyzer, 'styles.css'))->toBeTrue();
    expect($method->invoke($analyzer, 'script.js'))->toBeTrue();
    expect($method->invoke($analyzer, 'image.jpg'))->toBeTrue();
    expect($method->invoke($analyzer, 'image.png'))->toBeTrue();
    expect($method->invoke($analyzer, 'image.gif'))->toBeTrue();
    expect($method->invoke($analyzer, 'image.svg'))->toBeTrue();
    expect($method->invoke($analyzer, 'favicon.ico'))->toBeTrue();
    expect($method->invoke($analyzer, 'font.woff'))->toBeTrue();
    expect($method->invoke($analyzer, 'font.woff2'))->toBeTrue();

    // Non-static resources
    expect($method->invoke($analyzer, 'page.html'))->toBeFalse();
    expect($method->invoke($analyzer, 'page.php'))->toBeFalse();
    expect($method->invoke($analyzer, 'category/product'))->toBeFalse();
    expect($method->invoke($analyzer, '/'))->toBeFalse();
    expect($method->invoke($analyzer, 'api/data'))->toBeFalse();
});
