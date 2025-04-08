<?php

namespace Shah\Guardian\Detection\Analyzers;

use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Shah\Guardian\Detection\DetectionResult;

class HeaderAnalyzer
{
    protected const AI_CRAWLER_NAMES = [
        'gptbot', 'chatgpt', 'ccbot', 'claude', 'anthropic',
        'cohere', 'perplexity', 'bard', 'google ai', 'diffbot',
    ];

    public function analyze(Request $request): DetectionResult
    {
        $result = new DetectionResult(0, []);

        $userAgent = $request->header('User-Agent');
        $acceptHeader = $request->header('Accept');
        $acceptLanguage = $request->header('Accept-Language');
        $acceptEncoding = $request->header('Accept-Encoding');
        $xRequestedWith = $request->header('X-Requested-With');
        $xForwardedFor = $request->header('X-Forwarded-For');
        $via = $request->header('Via');

        if ($this->checkMissingUserAgent($userAgent, $result)) {
            return $result;
        }

        $deviceDetector = new DeviceDetector($userAgent);
        $deviceDetector->parse();

        if ($this->checkKnownBots($deviceDetector, $result)) {
            return $result;
        }

        $this->checkDesktopHeaders($deviceDetector, $acceptHeader, $acceptLanguage, $acceptEncoding, $result);
        $this->checkMobileHeaders($deviceDetector, $xRequestedWith, $result);
        $this->checkSuspiciousHeaders($xForwardedFor, $via, $result);
        $this->checkSimplifiedAcceptHeader($acceptHeader, $result);
        $this->checkCookieSupport($request, $result);

        return $result;
    }

    protected function checkMissingUserAgent(?string $userAgent, DetectionResult $result): bool
    {
        if (empty($userAgent)) {
            $result->addSignal('missing_user_agent', true);
            $result->increaseScore(80);

            return true;
        }

        return false;
    }

    protected function checkKnownBots(DeviceDetector $deviceDetector, DetectionResult $result): bool
    {
        // Early exit if not a bot
        if (! $deviceDetector->isBot()) {
            return false;
        }

        $botInfo = $deviceDetector->getBot();
        // Early exit if bot name is missing
        if (! isset($botInfo['name'])) {
            return false;
        }

        $botNameLower = strtolower($botInfo['name']);
        // Check for AI crawlers
        foreach (self::AI_CRAWLER_NAMES as $aiName) {
            if (strpos($botNameLower, $aiName) !== false) {
                $result->addSignal('known_ai_crawler', $botInfo['name']);
                $result->increaseScore(100);

                return true;
            }
        }

        // If not an AI crawler but still a bot
        $result->addSignal('known_bot', $botInfo['name']);
        $result->increaseScore(30);

        return false;
    }

    protected function checkDesktopHeaders(
        DeviceDetector $deviceDetector,
        ?string $acceptHeader,
        ?string $acceptLanguage,
        ?string $acceptEncoding,
        DetectionResult $result
    ): void {
        // Early exit if not desktop
        if (! $deviceDetector->isDesktop()) {
            return;
        }

        $browserFamily = $deviceDetector->getClient('name');
        // Early exit if browser family is missing
        if (! $browserFamily) {
            return;
        }

        // Check for missing headers
        if (empty($acceptHeader) || empty($acceptLanguage) || empty($acceptEncoding)) {
            $result->addSignal('missing_browser_headers');
            $result->increaseScore(40);
        }

        // Check suspicious Accept header for specific browsers
        if (! in_array($browserFamily, ['Chrome', 'Firefox', 'Safari', 'Edge'])) {
            return;
        }

        if ($acceptHeader === '*/*' || $acceptHeader === 'text/html') {
            $result->addSignal('suspicious_accept_header', $acceptHeader);
            $result->increaseScore(30);
        }
    }

    protected function checkMobileHeaders(
        DeviceDetector $deviceDetector,
        ?string $xRequestedWith,
        DetectionResult $result
    ): void {
        // Early exit if not mobile or is a tablet
        if (! $deviceDetector->isMobile() || $deviceDetector->isTablet()) {
            return;
        }

        if (empty($xRequestedWith)) {
            $result->addSignal('missing_mobile_headers');
            $result->increaseScore(20);
        }
    }

    protected function checkSuspiciousHeaders(?string $xForwardedFor, ?string $via, DetectionResult $result): void
    {
        if (! empty($xForwardedFor) && empty($via)) {
            $result->addSignal('inconsistent_proxy_headers', true);
            $result->increaseScore(15);
        }
    }

    protected function checkSimplifiedAcceptHeader(?string $acceptHeader, DetectionResult $result): void
    {
        if ($acceptHeader && (strlen($acceptHeader) < 10 || ! str_contains($acceptHeader, ','))) {
            $result->addSignal('simplified_accept_header', true);
            $result->increaseScore(25);
        }
    }

    protected function checkCookieSupport(Request $request, DetectionResult $result): void
    {
        if (! $request->hasCookie('_guardian_check') && $request->method() !== 'GET') {
            $result->addSignal('no_cookies');
            $result->increaseScore(10);
        }
    }
}
