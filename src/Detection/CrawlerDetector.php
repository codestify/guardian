<?php

namespace Shah\Guardian\Detection;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Shah\Guardian\Detection\Analyzers\BehaviouralAnalyzer;
use Shah\Guardian\Detection\Analyzers\HeaderAnalyzer;
use Shah\Guardian\Detection\Analyzers\RateLimitAnalyzer;
use Shah\Guardian\Detection\Analyzers\RequestPatternAnalyzer;

class CrawlerDetector
{
    /**
     * The analyzers used for detection.
     *
     * @var array
     */
    protected $analyzers = [];

    /**
     * The crawler detect instance.
     *
     * @var \Jaybizzle\CrawlerDetect\CrawlerDetect
     */
    protected $crawlerDetect;

    /**
     * Create a new crawler detector instance.
     *
     * @return void
     */
    public function __construct(
        HeaderAnalyzer $headerAnalyzer,
        RequestPatternAnalyzer $requestPatternAnalyzer,
        RateLimitAnalyzer $rateLimitAnalyzer,
        ?BehaviouralAnalyzer $behavioralAnalyzer = null
    ) {
        $this->analyzers = [
            'header' => $headerAnalyzer,
            'pattern' => $requestPatternAnalyzer,
            'rate_limit' => $rateLimitAnalyzer,
        ];

        // Only add if provided (for backward compatibility)
        if ($behavioralAnalyzer) {
            $this->analyzers['behavioral'] = $behavioralAnalyzer;
        }

        $this->crawlerDetect = new CrawlerDetect;
    }

    /**
     * Add an analyzer to the detector.
     *
     * @return $this
     */
    public function addAnalyzer(object $analyzer, string $key): self
    {
        $this->analyzers[$key] = $analyzer;

        return $this;
    }

    /**
     * Analyze a request for crawler signals.
     */
    public function analyze(Request $request): DetectionResult
    {
        // Skip if detection is disabled
        if (! config('guardian.detection.server_enabled', true)) {
            return new DetectionResult(0, []);
        }

        // Generate a unique fingerprint for this request
        $fingerprint = $this->generateRequestFingerprint($request);
        $cacheKey = "guardian_detection_{$fingerprint}";

        // Check cache first
        if (config('guardian.detection.use_cache', true) && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Quick check for known crawlers
        if ($this->isKnownCrawler($request)) {
            $result = new DetectionResult(100, ['known_crawler' => true]);
            $this->cacheResult($cacheKey, $result);

            return $result;
        }

        // Start with a fresh detection result
        $result = new DetectionResult(0);

        // Run analysis pipeline with early return optimization
        foreach ($this->analyzers as $key => $analyzer) {
            // Skip disabled analyzers
            if (! $this->isAnalyzerEnabled($key)) {
                continue;
            }

            try {
                // Run the analyzer
                $analysisResult = $analyzer->analyze($request);
                $result->merge($analysisResult);

                // Early return if we have a high confidence detection
                if ($result->score >= 80) {
                    $this->cacheResult($cacheKey, $result);
                    $this->logHighConfidenceDetection($request, $result);

                    return $result;
                }

            } catch (\Exception $e) {
                // Log analyzer errors but continue with other analyzers
                Log::warning("Guardian analyzer '{$key}' failed", [
                    'error' => $e->getMessage(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        // Cache the final result
        $this->cacheResult($cacheKey, $result);

        return $result;
    }

    /**
     * Generate a unique fingerprint for the request.
     */
    protected function generateRequestFingerprint(Request $request): string
    {
        // Basic components
        $components = [
            $request->ip(),
            $request->userAgent() ?? '',
        ];

        // Add extended components if detailed fingerprinting is enabled
        if (config('guardian.detection.detailed_fingerprinting', false)) {
            $components = array_merge($components, [
                $request->header('Accept') ?? '',
                $request->header('Accept-Language') ?? '',
                $request->header('Accept-Encoding') ?? '',
                $request->method(),
                $request->path(),
            ]);
        }

        return md5(implode('|', array_filter($components)));
    }

    /**
     * Determine if the request is from a known crawler.
     */
    protected function isKnownCrawler(Request $request): bool
    {
        $userAgent = $request->userAgent();

        if (empty($userAgent)) {
            return false;
        }

        // Check against AI crawler signatures
        $aiCrawlerSignatures = config('guardian.detection.ai_crawler_signatures', []);

        foreach ($aiCrawlerSignatures as $signature) {
            if (stripos($userAgent, $signature) !== false) {
                return true;
            }
        }

        // Use CrawlerDetect library as a fallback
        if ($this->crawlerDetect->isCrawler($userAgent)) {
            // Additional check to confirm if it's an AI crawler
            $crawler = $this->crawlerDetect->getMatches();

            // Known AI crawlers from CrawlerDetect
            $aiCrawlers = config('guardian.detection.ai_crawlers', ['GPTBot', 'CCBot', 'anthropic', 'Claude']);

            foreach ($aiCrawlers as $aiCrawler) {
                if (stripos($crawler, $aiCrawler) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if an analyzer is enabled in config.
     */
    protected function isAnalyzerEnabled(string $analyzer): bool
    {
        return config("guardian.detection.analyzers.{$analyzer}", true);
    }

    /**
     * Cache a detection result.
     */
    protected function cacheResult(string $key, DetectionResult $result): void
    {
        $duration = config('guardian.detection.cache_duration', 3600);

        Cache::put($key, $result, $duration);
    }

    /**
     * Log high confidence detections for monitoring.
     */
    protected function logHighConfidenceDetection(Request $request, DetectionResult $result): void
    {
        if (config('guardian.logging.high_confidence', true) && $result->score >= 80) {
            Log::channel(config('guardian.logging.channel', 'stack'))
                ->info('Guardian detected high confidence AI crawler', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'path' => $request->path(),
                    'score' => $result->score,
                    'signals' => $result->signals,
                ]);
        }
    }
}
