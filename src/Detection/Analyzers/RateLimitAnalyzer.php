<?php

namespace Shah\Guardian\Detection\Analyzers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Shah\Guardian\Detection\DetectionResult;

class RateLimitAnalyzer
{
    /** Threshold constants for easier maintenance */
    protected const SHORT_TERM_RATE_THRESHOLD = 30;   // Requests per minute

    protected const MEDIUM_TERM_RATE_THRESHOLD = 100; // Requests per 5 minutes

    protected const BURST_COUNT_THRESHOLD = 5;        // Requests in 2-second window

    protected const USER_AGENT_COUNT_THRESHOLD = 3;   // Unique user agents per IP

    /**
     * Analyze request rate for crawler behavior.
     */
    public function analyze(Request $request): DetectionResult
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';
        $identifier = md5($ip.'|'.$userAgent);

        $signals = [];
        $score = 0;

        // Check short-term rate (per minute)
        $shortTermRate = $this->getShortTermRate($identifier);
        if ($shortTermRate > self::SHORT_TERM_RATE_THRESHOLD) {
            $signals['high_request_rate_short_term'] = $shortTermRate;
            $score += min(50, ($shortTermRate - self::SHORT_TERM_RATE_THRESHOLD) * 2);
        }

        // Check medium-term rate (per 5 minutes)
        $mediumTermRate = $this->getMediumTermRate($identifier);
        if ($mediumTermRate > self::MEDIUM_TERM_RATE_THRESHOLD) {
            $signals['high_request_rate_medium_term'] = $mediumTermRate;
            $score += min(40, ($mediumTermRate - self::MEDIUM_TERM_RATE_THRESHOLD) / 5);
        }

        // Check bursting behavior
        $burstCount = $this->checkBurstBehavior($identifier);
        if ($burstCount > self::BURST_COUNT_THRESHOLD) {
            $signals['request_bursting'] = $burstCount;
            $score += min(60, $burstCount * 10);
        }

        // Check for multiple user agents from the same IP
        $userAgentCount = $this->checkMultipleUserAgents($ip);
        if ($userAgentCount > self::USER_AGENT_COUNT_THRESHOLD) {
            $signals['multiple_user_agents'] = $userAgentCount;
            $score += min(40, $userAgentCount * 10);
        }

        return new DetectionResult($score, $signals);
    }

    /**
     * Get the short-term request rate (1 minute).
     */
    protected function getShortTermRate(string $identifier): int
    {
        $key = "guardian_rate_short_{$identifier}";
        $count = Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, 60); // 1 minute
        }

        return $count;
    }

    /**
     * Get the medium-term request rate (5 minutes).
     */
    protected function getMediumTermRate(string $identifier): int
    {
        $key = "guardian_rate_medium_{$identifier}";
        $count = Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, 300); // 5 minutes
        }

        return $count;
    }

    /**
     * Check for burst behavior (multiple requests in quick succession).
     */
    protected function checkBurstBehavior(string $identifier): int
    {
        $key = "guardian_burst_{$identifier}";
        $burstData = Cache::get($key, [
            'count' => 0,
            'start_time' => microtime(true),
        ]);

        $currentTime = microtime(true);
        $windowDuration = 2.0; // 2 seconds

        // Reset if window has expired
        if ($currentTime - $burstData['start_time'] > $windowDuration) {
            $burstData = [
                'count' => 1,
                'start_time' => $currentTime,
            ];
            Cache::put($key, $burstData, 10);

            return 1; // Early exit for new window
        }

        // Increment count in existing window
        $burstData['count']++;
        Cache::put($key, $burstData, 10);

        return $burstData['count'];
    }

    /**
     * Check for multiple user agents from the same IP.
     */
    protected function checkMultipleUserAgents(string $ip): int
    {
        $key = "guardian_user_agents_{$ip}";
        $userAgents = Cache::get($key, []);
        $currentUserAgent = request()->userAgent() ?? '';

        // Early exit if UA is empty or already present
        if (empty($currentUserAgent) || in_array($currentUserAgent, $userAgents)) {
            return count($userAgents);
        }

        // Add new user agent
        $userAgents[] = $currentUserAgent;
        Cache::put($key, $userAgents, 1800); // 30 minutes

        return count($userAgents);
    }
}
