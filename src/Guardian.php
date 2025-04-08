<?php

namespace Shah\Guardian;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shah\Guardian\Detection\CrawlerDetector;
use Shah\Guardian\Detection\DetectionResult;
use Shah\Guardian\Models\GuardianLog;
use Shah\Guardian\Prevention\ContentProtector;
use Shah\Guardian\Prevention\PreventionEngine;
use Symfony\Component\HttpFoundation\Response;

class Guardian
{
    /**
     * The crawler detector instance.
     *
     * @var \Shah\Guardian\Detection\CrawlerDetector
     */
    protected $detector;

    /**
     * The prevention engine instance.
     *
     * @var \Shah\Guardian\Prevention\PreventionEngine
     */
    protected $preventionEngine;

    /**
     * The content protector instance.
     *
     * @var \Shah\Guardian\Prevention\ContentProtector
     */
    protected $contentProtector;

    /**
     * Create a new Guardian instance.
     *
     * @return void
     */
    public function __construct(
        CrawlerDetector $detector,
        PreventionEngine $preventionEngine,
        ContentProtector $contentProtector
    ) {
        $this->detector = $detector;
        $this->preventionEngine = $preventionEngine;
        $this->contentProtector = $contentProtector;
    }

    /**
     * Analyze a request for AI crawler signals.
     */
    public function analyze(Request $request): DetectionResult
    {
        // For mocking in tests
        if (app()->environment('testing') && app()->has('test_detection_result')) {
            return app('test_detection_result');
        }

        if (! config('guardian.detection.server_enabled', true)) {
            return new DetectionResult(0, []);
        }

        $result = $this->detector->analyze($request);

        // Log detection if enabled
        if (config('guardian.logging.enabled', true) && $result->isDetected()) {
            $this->logDetection($request, $result);
        }

        return $result;
    }

    /**
     * Apply prevention measures for detected crawler.
     */
    public function prevent(Request $request, Closure $next, ?DetectionResult $result = null): Response
    {
        // For mocking in tests
        if (app()->environment('testing') && app()->has('test_prevention_response')) {
            return app('test_prevention_response');
        }

        return $this->preventionEngine->prevent($request, $next, $result);
    }

    /**
     * Protect content from AI crawlers if applicable.
     */
    public function protectContent(Request $request, Response $response): Response
    {
        // For mocking in tests
        if (app()->environment('testing') && app()->has('test_protected_response')) {
            return app('test_protected_response');
        }

        if (! $this->shouldProtectContent($response)) {
            return $response;
        }

        $content = $response->getContent();
        $protectedContent = $this->protect($content);

        $newResponse = new Response(
            $protectedContent,
            $response->getStatusCode(),
            $response->headers->all()
        );

        return $newResponse;
    }

    /**
     * Determine if content protection should be applied to the response.
     */
    protected function shouldProtectContent(Response $response): bool
    {
        // Skip if content protection is not enabled
        if (! config('guardian.prevention.protect_content', false) && ! config('guardian.protect_all_content', false)) {
            return false;
        }

        // Only protect HTML responses
        if (! $this->isHtmlResponse($response)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the response is an HTML response.
     */
    protected function isHtmlResponse(Response $response): bool
    {
        if (! $response->headers->has('Content-Type')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type');

        return str_contains($contentType, 'text/html');
    }

    /**
     * Protect HTML content from AI crawlers.
     */
    public function protect(string $content): string
    {
        return $this->contentProtector->protect($content);
    }

    /**
     * Log a detection event.
     */
    protected function logDetection(Request $request, DetectionResult $result): void
    {
        $data = [
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'path' => $request->path(),
            'score' => $result->score,
            'confidence' => $result->confidenceLevel(),
            'signals' => $result->signals,
        ];

        $channel = config('guardian.logging.channel');

        if ($channel) {
            Log::channel($channel)->info('Guardian detected potential AI crawler', $data);
        } else {
            Log::info('Guardian detected potential AI crawler', $data);
        }

        // Store in database if enabled
        if (config('guardian.logging.database', false)) {
            GuardianLog::create([
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'path' => $request->path(),
                'score' => $result->score,
                'signals' => json_encode($result->signals),
                'detection_type' => 'server',
            ]);
        }
    }

    /**
     * Process a client-side detection report.
     */
    public function processClientReport(Request $request): bool
    {
        try {
            $data = $request->all();

            // Validate required fields
            if (! isset($data['signals']) || ! is_array($data['signals']) || empty($data['signals'])) {
                return false;
            }

            $score = $this->calculateClientReportScore($data['signals']);

            // Log to database if enabled
            if (config('guardian.logging.database', false) && $score > 0) {
                GuardianLog::create([
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'path' => $data['path'] ?? $request->path(),
                    'score' => $score,
                    'signals' => json_encode($data['signals']),
                    'detection_type' => 'client',
                ]);
            }

            // Log to channel if enabled
            if (config('guardian.logging.enabled', true) && $score >= config('guardian.detection.threshold', 60)) {
                $channel = config('guardian.logging.channel');
                $logData = [
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'path' => $data['path'] ?? $request->path(),
                    'score' => $score,
                    'signals' => $data['signals'],
                ];

                if ($channel) {
                    Log::channel($channel)->info('Guardian client-side detection', $logData);
                } else {
                    Log::info('Guardian client-side detection', $logData);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing client report', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return false;
        }
    }

    /**
     * Calculate a score from client-side signals.
     */
    protected function calculateClientReportScore(array $signals): int
    {
        // Define signal weights
        $weights = [
            'webdriver' => 80,
            'phantom' => 80,
            'nightmare' => 80,
            'chrome_automation' => 75,
            'no_languages' => 40,
            'no_mouse_movement' => 30,
            'no_clicks' => 25,
            'no_keyboard' => 25,
            'no_scroll' => 20,
            'canvas_blocked' => 40,
            'consistent_click_timing' => 50,
            'mechanical_scrolling' => 40,
            'perfectly_aligned_clicks' => 60,
            'fake_chrome' => 50,
            'fake_firefox' => 50,
            'canvas_error' => 30,
            'canvas_context_unavailable' => 30,
            'no_localstorage' => 30,
            'no_sessionstorage' => 30,
            'cookies_disabled' => 30,
            'zero_dimensions' => 50,
            'linear_click_pattern' => 45,
            'identical_scroll_jumps' => 40,
            'non_integer_pixel_ratio' => 10,
            'default' => 20, // Default weight for unknown signals
        ];

        $score = 0;

        // Calculate score based on signals
        foreach ($signals as $signal) {
            $weight = $weights[$signal] ?? $weights['default'];
            $score += $weight;
        }

        // Cap score at 100
        return min(100, $score);
    }
}
