<?php

namespace Shah\Guardian\Detection\Analyzers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Shah\Guardian\Detection\DetectionResult;

class RequestPatternAnalyzer
{
    /**
     * Analyze request patterns for crawler behavior.
     */
    public function analyze(Request $request): DetectionResult
    {
        $result = new DetectionResult(0, []);
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';

        // Analyze sequential access patterns
        $this->analyzeSequentialAccess($ip, $request, $result);

        // Analyze resource consumption patterns
        $this->analyzeResourceConsumption($ip, $userAgent, $request, $result);

        // Analyze request speed patterns
        $this->analyzeRequestSpeed($ip, $userAgent, $result);

        // Analyze navigation depth patterns
        $this->analyzeNavigationDepth($ip, $userAgent, $request, $result);

        return $result;
    }

    /**
     * Analyze sequential access patterns.
     */
    protected function analyzeSequentialAccess(string $ip, Request $request, DetectionResult $result): void
    {
        $cacheKey = "guardian_paths_{$ip}";
        $paths = Cache::get($cacheKey, []);

        // Store current path with timestamp
        $currentPath = $request->path();
        $paths[] = ['path' => $currentPath, 'time' => time()];

        // Filter paths to keep only the last 15 minutes
        $paths = array_filter($paths, fn ($entry) => time() - $entry['time'] < 900);

        // Early exit if not enough paths for analysis
        if (count($paths) < 5) {
            Cache::put($cacheKey, $paths, 900);

            return;
        }

        // Check for sequential numeric or alphabetical patterns
        $numericSequenceCount = 0;
        $alphaSequenceCount = 0;
        for ($i = 0; $i < count($paths) - 1; $i++) {
            $currentParts = explode('/', $paths[$i]['path']);
            $nextParts = explode('/', $paths[$i + 1]['path']);

            // Check numeric sequence (e.g., /page/1 -> /page/2)
            if (isset($currentParts[1], $nextParts[1]) &&
                is_numeric($currentParts[1]) && is_numeric($nextParts[1]) &&
                intval($nextParts[1]) - intval($currentParts[1]) === 1) {
                $numericSequenceCount++;
            }

            // Check alphabetical sequence (e.g., /a -> /b)
            if (isset($currentParts[0], $nextParts[0]) &&
                $currentParts[0] === $nextParts[0] &&
                isset($currentParts[1][0], $nextParts[1][0]) &&
                ord($nextParts[1][0]) - ord($currentParts[1][0]) === 1) {
                $alphaSequenceCount++;
            }
        }

        // Add signals and score for sequential patterns
        if ($numericSequenceCount >= 3) {
            $result->addSignal('sequential_numeric_access', $numericSequenceCount);
            $result->increaseScore(min(40, $numericSequenceCount * 10));
        }
        if ($alphaSequenceCount >= 3) {
            $result->addSignal('sequential_alpha_access', $alphaSequenceCount);
            $result->increaseScore(min(40, $alphaSequenceCount * 10));
        }

        Cache::put($cacheKey, $paths, 900);
    }

    /**
     * Analyze resource consumption patterns.
     */
    protected function analyzeResourceConsumption(string $ip, ?string $userAgent, Request $request, DetectionResult $result): void
    {
        $cacheKey = "guardian_resources_{$ip}";
        $resources = Cache::get($cacheKey, [
            'pages' => 0,
            'css' => 0,
            'js' => 0,
            'images' => 0,
            'last_check' => 0,
        ]);

        // Determine resource type
        $path = $request->path();
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $acceptHeader = $request->header('Accept');

        if ($extension === 'css') {
            $resources['css']++;
        } elseif ($extension === 'js') {
            $resources['js']++;
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) {
            $resources['images']++;
        } elseif ($acceptHeader && str_contains($acceptHeader, 'text/html')) {
            $resources['pages']++;
        }

        // Early exit if not enough pages or too soon since last check
        if ($resources['pages'] < 5 || time() - $resources['last_check'] <= 60) {
            Cache::put($cacheKey, $resources, 1800);

            return;
        }

        $resources['last_check'] = time();

        // Check for missing resource types
        if ($resources['css'] === 0 && ! str_contains($path, 'api')) {
            $result->addSignal('no_css_requests', true);
            $result->increaseScore(25);
        }
        if ($resources['js'] === 0 && ! str_contains($path, 'api')) {
            $result->addSignal('no_js_requests', true);
            $result->increaseScore(25);
        }
        if ($resources['images'] === 0 && ! str_contains($path, 'api')) {
            $result->addSignal('no_image_requests', true);
            $result->increaseScore(20);
        }

        // Check resource ratio
        $totalResources = $resources['css'] + $resources['js'] + $resources['images'];
        if ($resources['pages'] > 0 && $totalResources / $resources['pages'] < 0.5) {
            $result->addSignal('low_resource_ratio', $totalResources / $resources['pages']);
            $result->increaseScore(30);
        }

        Cache::put($cacheKey, $resources, 1800);
    }

    /**
     * Analyze request speed patterns.
     */
    protected function analyzeRequestSpeed(string $ip, ?string $userAgent, DetectionResult $result): void
    {
        $cacheKey = "guardian_requests_{$ip}";
        $requests = Cache::get($cacheKey, []);

        $currentTime = microtime(true);
        $requests[] = $currentTime;
        if (count($requests) > 20) {
            $requests = array_slice($requests, -20);
        }

        // Early exit if not enough requests
        if (count($requests) < 5) {
            Cache::put($cacheKey, $requests, 300);

            return;
        }

        // Calculate intervals
        $intervals = [];
        for ($i = 1; $i < count($requests); $i++) {
            $intervals[] = $requests[$i] - $requests[$i - 1];
        }
        $avgInterval = array_sum($intervals) / count($intervals);

        // Check for too fast requests
        if ($avgInterval < 0.5) {
            $result->addSignal('too_fast_requests', $avgInterval);
            $result->increaseScore(min(50, 50 * (0.5 - $avgInterval)));
        }

        // Check for consistent timing
        if (count($intervals) >= 5) {
            $variation = $this->calculateVariation($intervals);
            if ($variation < 0.1) {
                $result->addSignal('consistent_timing', $variation);
                $result->increaseScore(35);
            }
        }

        Cache::put($cacheKey, $requests, 300);
    }

    /**
     * Calculate the coefficient of variation.
     */
    protected function calculateVariation(array $values): float
    {
        $count = count($values);
        if ($count <= 1) {
            return 0.0;
        }
        $mean = array_sum($values) / $count;
        if ($mean === 0.0) {
            return 0.0;
        }
        $variance = array_sum(array_map(fn ($v) => pow($v - $mean, 2), $values)) / $count;

        return sqrt($variance) / $mean;
    }

    /**
     * Analyze navigation depth patterns.
     */
    protected function analyzeNavigationDepth(string $ip, ?string $userAgent, Request $request, DetectionResult $result): void
    {
        $cacheKey = "guardian_depth_{$ip}";
        $navigationData = Cache::get($cacheKey, [
            'max_depth' => 0,
            'depths' => [],
            'referers' => [],
        ]);

        // Calculate current depth
        $path = $request->path();
        $depth = substr_count($path, '/') + 1;
        $navigationData['max_depth'] = max($navigationData['max_depth'], $depth);
        $navigationData['depths'][$depth] = ($navigationData['depths'][$depth] ?? 0) + 1;

        // Track referer
        $referer = $request->header('Referer');
        if ($referer) {
            $navigationData['referers'][] = $referer;
            $navigationData['referers'] = array_slice($navigationData['referers'], -10);
        }

        // Early exit if not enough requests
        $totalRequests = array_sum($navigationData['depths']);
        if ($totalRequests < 5) {
            Cache::put($cacheKey, $navigationData, 1800);

            return;
        }

        // Check deep page ratio
        if ($navigationData['max_depth'] >= 4 && $totalRequests >= 10) {
            $deepPages = array_sum(array_filter(
                $navigationData['depths'],
                fn ($d) => $d >= 4,
                ARRAY_FILTER_USE_KEY
            ));
            $deepRatio = $deepPages / $totalRequests;
            if ($deepRatio > 0.7) {
                $result->addSignal('deep_page_ratio', $deepRatio);
                $result->increaseScore(min(35, $deepRatio * 50));
            }
        }

        // Check referer patterns
        if (empty($navigationData['referers']) && $totalRequests > 5) {
            $result->addSignal('no_referers', true);
            $result->increaseScore(15);
        } elseif (! empty($navigationData['referers'])) {
            $domains = array_unique(array_map(
                fn ($ref) => parse_url($ref, PHP_URL_HOST) ?? '',
                $navigationData['referers']
            ));
            $uniqueDomains = count(array_filter($domains));
            if ($uniqueDomains > 3 && $totalRequests < 10) {
                $result->addSignal('multiple_referer_domains', $uniqueDomains);
                $result->increaseScore(20);
            }
        }

        Cache::put($cacheKey, $navigationData, 1800);
    }
}
