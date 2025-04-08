<?php

namespace Shah\Guardian\Prevention;

use Closure;
use Illuminate\Http\Request;
use Shah\Guardian\Detection\DetectionResult;
use Symfony\Component\HttpFoundation\Response;

class PreventionEngine
{
    /**
     * Default prevention thresholds for adaptive responses.
     */
    protected const DEFAULT_THRESHOLDS = [
        'block' => 90,
        'honeypot' => 75,
        'alternate' => 60,
        'delay' => 40,
    ];

    /**
     * Placeholder messages for alternate content.
     */
    protected const PLACEHOLDERS = [
        'This content is not available for automated access.',
        'This information requires authentication to access.',
        'Content only available to registered users.',
        'Please log in to view this content.',
        'This section is protected against automated access.',
    ];

    protected ContentProtector $contentProtector;

    protected ?HoneypotGenerator $honeypotGenerator;

    public function __construct(ContentProtector $contentProtector, ?HoneypotGenerator $honeypotGenerator = null)
    {
        $this->contentProtector = $contentProtector;
        $this->honeypotGenerator = $honeypotGenerator;
    }

    /**
     * Handle the incoming request without prevention (placeholder).
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Apply prevention measures based on detection result or configured strategy.
     */
    public function prevent(Request $request, Closure $next, ?DetectionResult $result = null): Response
    {
        $strategy = config('guardian.prevention.strategy', 'alternate_content');

        // Use adaptive strategy if enabled and detection result is provided
        if (config('guardian.prevention.adaptive', false) && $result) {
            return $this->applyAdaptiveStrategy($request, $next, $result);
        }

        // Apply static strategy
        return match ($strategy) {
            'block' => $this->blockResponse($request),
            'honeypot' => $this->honeypotResponse($request, $next),
            'delay' => $this->delayResponse($request, $next),
            'alternate_content' => $this->alternateContentResponse($request, $next),
            default => $this->handleInvalidStrategy($request, $next, $strategy),
        };
    }

    /**
     * Apply an adaptive response based on detection score.
     */
    protected function applyAdaptiveStrategy(Request $request, Closure $next, DetectionResult $result): Response
    {
        $thresholds = config('guardian.prevention.thresholds', self::DEFAULT_THRESHOLDS);
        $score = $result->score;

        if ($score >= $thresholds['block']) {
            return $this->blockResponse($request);
        }
        if ($score >= $thresholds['honeypot']) {
            return $this->honeypotGenerator ? $this->honeypotResponse($request, $next) : $this->alternateContentResponse($request, $next);
        }
        if ($score >= $thresholds['alternate']) {
            return $this->alternateContentResponse($request, $next);
        }
        if ($score >= $thresholds['delay']) {
            return $this->delayResponse($request, $next);
        }

        $response = $next($request);
        $this->protectContent($response);

        return $response;
    }

    /**
     * Block the request with a 403 response.
     */
    protected function blockResponse(Request $request): Response
    {
        return response('Blocked', 403)
            ->withHeaders([
                'X-Robots-Tag' => 'noindex, nofollow',
                'X-Guardian-Protected' => 'true',
            ]);
    }

    /**
     * Serve a honeypot response to trap crawlers.
     */
    protected function honeypotResponse(Request $request, Closure $next): Response
    {
        if (! $this->honeypotGenerator) {
            return $this->alternateContentResponse($request, $next);
        }

        $uniqueId = uniqid('guardian_', true);
        $content = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="guardian-protected" content="true">
    <meta name="guardian-token" content="{$uniqueId}">
    <title>Internal Page - RESTRICTED ACCESS</title>
</head>
<body class="guardian-protected">
    <div class="guardian-placeholder">
        <h1>Authentication Required</h1>
        <p>This content requires authentication to access</p>
        <div class="guardian-tracking" style="display: none;">
            <img src='/guardian-track/{$uniqueId}' alt='tracking'>
            <input type='hidden' name='guardian_token' value='{$uniqueId}'>
            <a href='/internal/document/{$uniqueId}'>Internal Document</a>
            <a href='/private/content/{$uniqueId}'>Private Content</a>
            <a href='/member/access/{$uniqueId}'>Member Access</a>
            <a href='/restricted/data/{$uniqueId}'>Restricted Data</a>
        </div>
    </div>
</body>
</html>
HTML;

        return response($content)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->withHeaders([
                'X-Robots-Tag' => 'noindex, nofollow',
                'X-Guardian-Protected' => 'true',
                'X-Guardian-Token' => $uniqueId,
            ]);
    }

    /**
     * Apply a delayed response to deter crawlers.
     */
    protected function delayResponse(Request $request, Closure $next): Response
    {
        $delaySeconds = (float) config('guardian.prevention.delay_seconds', 2);
        $startTime = microtime(true);

        // Ensure minimum delay is applied
        $minDelay = max(0.5, $delaySeconds);
        usleep((int) ($minDelay * 1000000));

        $response = $next($request);
        if (! $this->isHtmlResponse($response)) {
            return $response->withHeaders([
                'X-Guardian-Protected' => 'true',
                'X-Guardian-Delay' => (string) (microtime(true) - $startTime),
            ]);
        }

        $content = $response->getContent();
        $dom = new \DOMDocument;
        $internalErrors = libxml_use_internal_errors(true);
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors($internalErrors);

        // Add guardian-protected class to body
        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            $body = $dom->createElement('body');
            $html = $dom->getElementsByTagName('html')->item(0);
            if (! $html) {
                $html = $dom->createElement('html');
                $dom->appendChild($html);
            }
            $html->appendChild($body);
        }

        // Add guardian-protected class to body
        $classes = $body->getAttribute('class');
        $classes = trim($classes ? $classes.' guardian-protected' : 'guardian-protected');
        $body->setAttribute('class', $classes);

        // Add meta tag for guardian protection
        $head = $dom->getElementsByTagName('head')->item(0);
        if (! $head) {
            $head = $dom->createElement('head');
            $html = $dom->getElementsByTagName('html')->item(0);
            $html->insertBefore($head, $html->firstChild);
        }

        $meta = $dom->createElement('meta');
        $meta->setAttribute('name', 'guardian-protected');
        $meta->setAttribute('content', 'true');
        $head->appendChild($meta);

        // Add guardian-protected class to all content nodes
        $xpath = new \DOMXPath($dom);
        $contentNodes = $xpath->query(
            '//article | //section | //div[contains(@class, "content")] | '.
                '//div[contains(@class, "main")] | //main | //p | '.
                '//div[contains(@class, "article")] | //div[contains(@class, "post")]'
        );

        foreach ($contentNodes as $node) {
            if ($node instanceof \DOMElement) {
                $classes = $node->getAttribute('class');
                $classes = trim($classes ? $classes.' guardian-protected' : 'guardian-protected');
                $node->setAttribute('class', $classes);
            }
        }

        $content = $this->contentProtector->protect($dom->saveHTML());

        // Ensure total delay meets minimum requirement
        $currentDelay = microtime(true) - $startTime;
        if ($currentDelay < $minDelay) {
            usleep((int) (($minDelay - $currentDelay) * 1000000));
        }

        return $response->setContent($content)
            ->withHeaders([
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Guardian-Protected' => 'true',
                'X-Guardian-Delay' => (string) (microtime(true) - $startTime),
            ]);
    }

    /**
     * Serve alternate content to obscure real data.
     */
    protected function alternateContentResponse(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->isHtmlResponse($response)) {
            return $response;
        }

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('X-Guardian-Protected', 'true');

        $dom = new \DOMDocument;
        $dom->loadHTML($response->getContent(), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Add guardian metadata
        $head = $dom->getElementsByTagName('head')->item(0);
        if (! $head) {
            $head = $dom->createElement('head');
            $dom->documentElement->insertBefore($head, $dom->documentElement->firstChild);
        }

        $meta = $dom->createElement('meta');
        $meta->setAttribute('name', 'guardian-protected');
        $meta->setAttribute('content', 'true');
        $head->appendChild($meta);

        $robots = $dom->createElement('meta');
        $robots->setAttribute('name', 'robots');
        $robots->setAttribute('content', 'noindex, nofollow');
        $head->appendChild($robots);

        // Replace body content with placeholder
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $body->setAttribute('class', 'guardian-protected');

            // Clear existing content
            while ($body->hasChildNodes()) {
                $body->removeChild($body->firstChild);
            }

            $placeholder = $dom->createElement('div');
            $placeholder->setAttribute('class', 'guardian-placeholder');
            $placeholder->textContent = 'This content is not available for automated access.';
            $body->appendChild($placeholder);
        }

        $response->setContent($dom->saveHTML());

        return $response;
    }

    /**
     * Protect content in the response.
     */
    protected function protectContent(Response $response): void
    {
        if (! $this->isHtmlResponse($response)) {
            return;
        }

        $content = $this->contentProtector->protect($this->addProtectedClass($response->getContent()));
        $response->setContent($content);
    }

    /**
     * Add the guardian-protected class to the body element.
     */
    protected function addProtectedClass(string $content): string
    {
        $dom = new \DOMDocument;
        $internalErrors = libxml_use_internal_errors(true);
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors($internalErrors);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            $body = $dom->createElement('body');
            $html = $dom->getElementsByTagName('html')->item(0) ?? $dom->createElement('html');
            $html->appendChild($body);
            if (! $dom->getElementsByTagName('html')->item(0)) {
                $dom->appendChild($html);
            }
        }

        $classes = trim($body->getAttribute('class').' guardian-protected');
        $body->setAttribute('class', $classes);

        return $dom->saveHTML();
    }

    /**
     * Handle invalid strategy by logging and falling back to delay.
     */
    protected function handleInvalidStrategy(Request $request, Closure $next, string $strategy): Response
    {
        return $this->delayResponse($request, $next);
    }

    /**
     * Check if the response contains HTML content.
     */
    protected function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains(strtolower($contentType), 'text/html') ||
            str_contains(strtolower($contentType), 'application/xhtml+xml') ||
            (! $contentType && str_contains(strtolower($response->getContent()), '<!doctype html'));
    }

    /**
     * Add guardian-protected class to a DOM node.
     */
    protected function addGuardianClass(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $classes = $node->getAttribute('class');
            $classes = trim($classes ? $classes.' guardian-protected' : 'guardian-protected');
            $node->setAttribute('class', $classes);
        }
    }

    /**
     * Append a fallback placeholder when no content nodes are found.
     */
    protected function appendFallbackPlaceholder(\DOMDocument $dom): void
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            $body = $dom->createElement('body');
            $html = $dom->getElementsByTagName('html')->item(0);
            if (! $html) {
                $html = $dom->createElement('html');
                $dom->appendChild($html);
            }
            $html->appendChild($body);
        }

        $div = $dom->createElement('div');
        $classes = 'guardian-protected guardian-placeholder';
        $div->setAttribute('class', $classes);
        $text = $dom->createTextNode($this->getRandomPlaceholder());
        $div->appendChild($text);
        $body->appendChild($div);
    }

    /**
     * Replace node content with a placeholder.
     */
    protected function replaceNodeContent(\DOMNode $node, \DOMDocument $dom): void
    {
        if (! ($node instanceof \DOMElement)) {
            return;
        }

        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }

        $text = $dom->createTextNode($this->getRandomPlaceholder());
        $node->appendChild($text);
    }

    /**
     * Get a random placeholder message.
     */
    protected function getRandomPlaceholder(): string
    {
        return self::PLACEHOLDERS[array_rand(self::PLACEHOLDERS)];
    }
}
