<?php

namespace Shah\Guardian\Tests\Unit;

use Shah\Guardian\Prevention\ContentProtector;

beforeEach(function () {
    $this->protector = new ContentProtector;

    // Reset config values for each test
    config(['guardian.prevention.content_protection.text_fragmentation' => null]);
    config(['guardian.prevention.content_protection.css_obfuscation' => null]);
    config(['guardian.prevention.content_protection.add_honeytokens' => null]);
    config(['guardian.prevention.content_protection.css_layers' => null]);
});

afterEach(function () {
    // Clean up
    config(['guardian.prevention.content_protection.text_fragmentation' => null]);
    config(['guardian.prevention.content_protection.css_obfuscation' => null]);
    config(['guardian.prevention.content_protection.add_honeytokens' => null]);
    config(['guardian.prevention.content_protection.css_layers' => null]);
});

it('returns non-HTML content unmodified', function () {
    $nonHtmlContent = [
        'Plain text content',
        '{"json": "data"}',
        '<?xml version="1.0"?><root><item>XML data</item></root>',
    ];

    foreach ($nonHtmlContent as $content) {
        $result = $this->protector->protect($content);
        expect($result)->toBe($content);
    }
});

it('fragments text content when enabled', function () {
    // Enable text fragmentation
    config(['guardian.prevention.content_protection.text_fragmentation' => true]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>This is a test paragraph with content that should be protected.</p></body></html>';

    $result = $this->protector->protect($html);

    // Should contain span elements with gdn- classes
    expect($result)->toContain('<span class="gdn-');

    // The original text should still be present despite fragmentation
    expect($result)->toContain('This is a test paragraph with content that should be protected');

    // Should generate valid HTML
    $dom = new \DOMDocument;
    $loadResult = @$dom->loadHTML($result);
    expect($loadResult)->toBeTrue();
});

it('skips text fragmentation when disabled', function () {
    // Disable text fragmentation
    config(['guardian.prevention.content_protection.text_fragmentation' => false]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>This is a test paragraph.</p></body></html>';

    $result = $this->protector->protect($html);

    // Should not contain fragmentation spans
    expect($result)->not->toContain('<span class="gdn-');
});

it('only fragments text nodes longer than minimum length', function () {
    config(['guardian.prevention.content_protection.text_fragmentation' => true]);

    // HTML with short and long text
    $html = '<!DOCTYPE html><html><body>
        <p>Short.</p>
        <p>This is a much longer paragraph that should be fragmented because it exceeds the minimum length threshold for fragmentation.</p>
    </body></html>';

    $result = $this->protector->protect($html);

    // Load both into DOM for comparison
    $originalDom = new \DOMDocument;
    @$originalDom->loadHTML($html);

    $resultDom = new \DOMDocument;
    @$resultDom->loadHTML($result);

    // Count spans in result
    $spans = $resultDom->getElementsByTagName('span');

    // Only the long paragraph should be fragmented
    expect($spans->length)->toBeGreaterThan(0);

    // The short text should still be directly in a p tag without spans
    $shortTextNode = false;
    foreach ($resultDom->getElementsByTagName('p') as $p) {
        if (trim($p->textContent) === 'Short.') {
            // Check if it has no span children
            $hasNoSpans = true;
            foreach ($p->childNodes as $child) {
                if ($child->nodeName === 'span') {
                    $hasNoSpans = false;
                    break;
                }
            }
            $shortTextNode = $hasNoSpans;
            break;
        }
    }
    expect($shortTextNode)->toBeTrue();
});

it('applies CSS obfuscation when enabled', function () {
    // Enable CSS obfuscation
    config(['guardian.prevention.content_protection.css_obfuscation' => true]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><article><p>This is content in an article.</p></article></body></html>';

    $result = $this->protector->protect($html);

    // Should add CSS style element
    expect($result)->toContain('<style');

    // Should contain direction and unicode-bidi rules
    expect($result)->toContain('direction: rtl');
    expect($result)->toContain('unicode-bidi: bidi-override');

    // Should add container class to article
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);

    $articles = $dom->getElementsByTagName('article');
    $hasContainerClass = false;

    foreach ($articles as $article) {
        $class = $article->getAttribute('class');
        if (strpos($class, 'gdn-') !== false && strpos($class, '-container') !== false) {
            $hasContainerClass = true;
            break;
        }
    }

    expect($hasContainerClass)->toBeTrue();
});

it('skips CSS obfuscation when disabled', function () {
    // Disable CSS obfuscation
    config(['guardian.prevention.content_protection.css_obfuscation' => false]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><article><p>This is content in an article.</p></article></body></html>';

    $result = $this->protector->protect($html);

    // Should not contain direction and unicode-bidi rules
    expect($result)->not->toContain('direction: rtl');
    expect($result)->not->toContain('unicode-bidi: bidi-override');

    // Articles should not have container classes
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);

    $articles = $dom->getElementsByTagName('article');
    $hasContainerClass = false;

    foreach ($articles as $article) {
        $class = $article->getAttribute('class');
        if (strpos($class, 'gdn-') !== false && strpos($class, '-container') !== false) {
            $hasContainerClass = true;
            break;
        }
    }

    expect($hasContainerClass)->toBeFalse();
});

it('adds honeytokens when enabled', function () {
    // Enable honeytokens
    config(['guardian.prevention.content_protection.add_honeytokens' => true]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Test content.</p></body></html>';

    $result = $this->protector->protect($html);

    // Should contain hidden div elements with honeypot classes
    expect($result)->toContain('gdn-ht-');
    expect($result)->toContain('position:absolute; opacity:0;');
    expect($result)->toContain('class="guardian-honeypot"');

    // Should contain hidden content section
    expect($result)->toContain('class="hidden-content"');
    expect($result)->toContain('display:none');
    expect($result)->toContain('Restricted Content');
});

it('skips honeytokens when disabled', function () {
    // Disable honeytokens
    config(['guardian.prevention.content_protection.add_honeytokens' => false]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Test content.</p></body></html>';

    $result = $this->protector->protect($html);

    // Should not contain honeypot elements
    expect($result)->not->toContain('guardian-honeypot');
    expect($result)->not->toContain('class="hidden-content"');
});

it('always adds guardian metadata', function () {
    // Test with all protection features disabled
    config([
        'guardian.prevention.content_protection.text_fragmentation' => false,
        'guardian.prevention.content_protection.css_obfuscation' => false,
        'guardian.prevention.content_protection.add_honeytokens' => false,
    ]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Test content.</p></body></html>';

    $result = $this->protector->protect($html);

    // Should always add guardian metadata
    expect($result)->toContain('<meta name="guardian-protected"');
    expect($result)->toContain('<meta name="robots" content="noai, noimageai"');
});

it('always adds guardian script', function () {
    // Test with all protection features disabled
    config([
        'guardian.prevention.content_protection.text_fragmentation' => false,
        'guardian.prevention.content_protection.css_obfuscation' => false,
        'guardian.prevention.content_protection.add_honeytokens' => false,
    ]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Test content.</p></body></html>';

    $result = $this->protector->protect($html);

    // Should always add protection script
    expect($result)->toContain('<script');
    expect($result)->toContain('Guardian content protection script');

    // Script should include copy protection
    expect($result)->toContain('document.addEventListener(\'copy\'');
});

it('handles malformed HTML gracefully', function () {
    $malformedHtmlExamples = [
        // Missing doctype
        '<html><body><p>No doctype.</p></body></html>',

        // Unclosed tags
        '<html><body><p>Unclosed paragraph tag</body></html>',

        // Missing closing html
        '<html><body><p>Missing closing html</p></body>',

        // No html structure at all
        '<p>Just a paragraph</p>',

        // Mismatched tags
        '<html><body><div><p>Mismatched tags</div></p></body></html>',

        // Invalid nesting
        '<html><tr><td>Invalid nesting</td></tr></html>',

        // Invalid attributes
        '<html><body><p class=>Invalid attribute</p></body></html>',

        // Script with unescaped content
        '<html><head><script>if (a < b && c > d) { alert("test"); }</script></head><body>Test</body></html>',
    ];

    foreach ($malformedHtmlExamples as $html) {
        // Should not throw exceptions
        $result = $this->protector->protect($html);

        // Should return a non-empty string
        expect($result)->toBeString();
        expect($result)->not->toBeEmpty();
    }
});

it('preserves HTML structure and attributes', function () {
    $html = '<!DOCTYPE html><html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Test Page</title>
        <link rel="stylesheet" href="styles.css">
    </head>
    <body class="test-class" data-test="value">
        <header id="main-header">
            <h1>Page Title</h1>
            <nav>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/about">About</a></li>
                </ul>
            </nav>
        </header>
        <main>
            <article>
                <h2>Article Title</h2>
                <p>This is a paragraph with <strong>bold text</strong> and <em>emphasized text</em>.</p>
                <ul>
                    <li>List item 1</li>
                    <li>List item 2</li>
                </ul>
            </article>
        </main>
        <footer>
            &copy; 2025 Test Site
        </footer>
    </body>
    </html>';

    // Apply all protections
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
        'guardian.prevention.content_protection.add_honeytokens' => true,
    ]);

    $result = $this->protector->protect($html);

    // Parse both original and result HTML
    $originalDom = new \DOMDocument;
    @$originalDom->loadHTML($html);

    $resultDom = new \DOMDocument;
    @$resultDom->loadHTML($result);

    // Check structure is preserved
    expect($resultDom->getElementsByTagName('html')->length)->toBe(1);
    expect($resultDom->getElementsByTagName('head')->length)->toBe(1);
    expect($resultDom->getElementsByTagName('body')->length)->toBe(1);
    expect($resultDom->getElementsByTagName('header')->length)->toBe(1);
    expect($resultDom->getElementsByTagName('main')->length)->toBe(1);
    expect($resultDom->getElementsByTagName('footer')->length)->toBe(1);

    // Check attributes are preserved
    $resultHtml = $resultDom->getElementsByTagName('html')->item(0);
    expect($resultHtml->getAttribute('lang'))->toBe('en');

    $resultBody = $resultDom->getElementsByTagName('body')->item(0);
    expect($resultBody->getAttribute('class'))->toContain('test-class');
    expect($resultBody->getAttribute('data-test'))->toBe('value');

    $resultHeader = $resultDom->getElementsByTagName('header')->item(0);
    expect($resultHeader->getAttribute('id'))->toBe('main-header');

    // Check all headings are preserved
    expect($resultDom->getElementsByTagName('h1')->length)->toBe(1);
    expect($resultDom->getElementsByTagName('h2')->length)->toBeGreaterThanOrEqual(1);

    // Check links and navigation are preserved
    $links = $resultDom->getElementsByTagName('a');
    expect($links->length)->toBeGreaterThanOrEqual(2);

    // Check if we have the expected links
    $hasHomeLink = false;
    $hasAboutLink = false;

    for ($i = 0; $i < $links->length; $i++) {
        $href = $links->item($i)->getAttribute('href');
        if ($href === '/') {
            $hasHomeLink = true;
        }
        if ($href === '/about') {
            $hasAboutLink = true;
        }
    }

    expect($hasHomeLink)->toBeTrue();
    expect($hasAboutLink)->toBeTrue();

    // Check text formatting is preserved
    $strong = $resultDom->getElementsByTagName('strong');
    $em = $resultDom->getElementsByTagName('em');
    expect($strong->length)->toBe(1);
    expect($em->length)->toBe(1);
    expect($strong->item(0)->textContent)->toBe('bold text');
    expect($em->item(0)->textContent)->toBe('emphasized text');
});

it('handles UTF-8 and special characters correctly', function () {
    $html = '<!DOCTYPE html><html><head><title>UTF-8 Test</title></head><body>
        <p>UTF-8 characters: ñáéíóúüÑÁÉÍÓÚÜ</p>
        <p>CJK characters: 你好, こんにちは, 안녕하세요</p>
        <p>Symbols: ©®™€£¥§¶†‡※</p>
        <p>HTML entities: &lt;div&gt; &amp; &quot;quoted&quot; &apos;text&apos;</p>
    </body></html>';

    // Apply all protections
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
        'guardian.prevention.content_protection.add_honeytokens' => true,
    ]);

    $result = $this->protector->protect($html);

    // Parse result HTML
    $resultDom = new \DOMDocument;
    @$resultDom->loadHTML($result);

    // Check UTF-8 content
    $paragraphs = $resultDom->getElementsByTagName('p');
    $textContent = '';

    foreach ($paragraphs as $p) {
        $textContent .= $p->textContent;
    }

    // All special characters should be preserved
    expect($textContent)->toContain('ñáéíóúüÑÁÉÍÓÚÜ');
    expect($textContent)->toContain('你好');
    expect($textContent)->toContain('こんにちは');
    expect($textContent)->toContain('안녕하세요');
    expect($textContent)->toContain('©®™€£¥§¶†‡※');

    // Entities should be preserved or converted to their characters
    expect($textContent)->toContain('<div> & "quoted" \'text\'');
});

it('protects content in various HTML5 elements', function () {
    $html = '<!DOCTYPE html><html><head><title>HTML5 Elements</title></head><body>
        <main>
            <article>
                <p>Article content</p>
            </article>
            <section>
                <p>Section content</p>
            </section>
            <aside>
                <p>Aside content</p>
            </aside>
            <details>
                <summary>Summary text</summary>
                <p>Details content</p>
            </details>
            <figure>
                <figcaption>Figure caption</figcaption>
            </figure>
        </main>
        <footer>
            <p>Footer content</p>
        </footer>
    </body></html>';

    // Enable text fragmentation
    config(['guardian.prevention.content_protection.text_fragmentation' => true]);

    $result = $this->protector->protect($html);

    // All original content should be preserved
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);

    $textContent = $dom->textContent;

    expect($textContent)->toContain('Article content');
    expect($textContent)->toContain('Section content');
    expect($textContent)->toContain('Aside content');
    expect($textContent)->toContain('Summary text');
    expect($textContent)->toContain('Details content');
    expect($textContent)->toContain('Figure caption');
    expect($textContent)->toContain('Footer content');
});

it('handles extremely large HTML documents', function () {
    // Generate a large HTML document with many paragraphs
    $html = '<!DOCTYPE html><html><head><title>Large Document</title></head><body>';

    // Add 1000 paragraphs
    for ($i = 0; $i < 1000; $i++) {
        $html .= "<p>This is paragraph {$i} with some content that should be protected from AI crawler harvesting.</p>";
    }

    $html .= '</body></html>';

    // Apply protection
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
        'guardian.prevention.content_protection.add_honeytokens' => true,
    ]);

    // Measure execution time
    $startTime = microtime(true);
    $result = $this->protector->protect($html);
    $endTime = microtime(true);

    // Should process in reasonable time (< 3 seconds on most systems)
    expect($endTime - $startTime)->toBeLessThan(3.0);

    // Result should be valid HTML
    $dom = new \DOMDocument;
    $parseResult = @$dom->loadHTML($result);
    expect($parseResult)->toBeTrue();

    // All paragraphs should be present
    $paragraphs = $dom->getElementsByTagName('p');

    // Original paragraphs + hidden content paragraph
    expect($paragraphs->length)->toBeGreaterThanOrEqual(1000);
});

it('adds Javascript with copy protection', function () {
    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Test content.</p></body></html>';

    $result = $this->protector->protect($html);

    // Should contain copy event listener
    expect($result)->toContain('document.addEventListener(\'copy\'');

    // Should include code to modify clipboard data
    expect($result)->toContain('e.clipboardData.setData');
    expect($result)->toContain('e.preventDefault()');

    // Should include automation detection
    expect($result)->toContain('detectAutomation');
    expect($result)->toContain('navigator.webdriver');
});

it('skips obfuscation for navigation, header, and footer elements', function () {
    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
        <nav class="main-nav"><ul><li><a href="/">Home</a></li></ul></nav>
        <header class="site-header"><h1>Site Title</h1></header>
        <main><p>Main content that should be protected.</p></main>
        <footer class="site-footer"><p>Footer content</p></footer>
    </body></html>';

    // Enable CSS obfuscation
    config(['guardian.prevention.content_protection.css_obfuscation' => true]);

    $result = $this->protector->protect($html);

    // Check the result HTML directly for expected patterns
    expect($result)->toContain('<nav class="main-nav"');
    expect($result)->toContain('<header class="site-header"');
    expect($result)->toContain('<footer class="site-footer"');

    // Main should have container class
    expect($result)->toContain('<main class="');
    expect($result)->toContain('gdn-');
    expect($result)->toContain('-container');
});

it('creates page-unique class names for obfuscation', function () {
    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
        <article><p>Article 1 content.</p></article>
        <article><p>Article 2 content.</p></article>
    </body></html>';

    // Enable CSS obfuscation
    config(['guardian.prevention.content_protection.css_obfuscation' => true]);

    // Generate two protected versions
    $result1 = $this->protector->protect($html);
    $result2 = $this->protector->protect($html);

    // Extract container class names from both results
    preg_match('/class="([^"]*gdn-[a-z0-9]+-container[^"]*)"/', $result1, $matches1);
    preg_match('/class="([^"]*gdn-[a-z0-9]+-container[^"]*)"/', $result2, $matches2);

    $class1 = $matches1[1] ?? '';
    $class2 = $matches2[1] ?? '';

    // Class names should be different for each page load
    expect($class1)->not->toBe($class2);
});

it('adds meta tags to head or creates head if needed', function () {
    // HTML without head tag
    $htmlNoHead = '<html><body><p>No head tag</p></body></html>';

    $result = $this->protector->protect($htmlNoHead);

    // Should create head and add meta tags
    expect($result)->toContain('<head>');
    expect($result)->toContain('<meta name="guardian-protected"');

    // HTML with no html structure at all
    $htmlNoStructure = '<p>Just a paragraph</p>';

    $result2 = $this->protector->protect($htmlNoStructure);

    // Should still add meta tags
    expect($result2)->toContain('<meta name="guardian-protected"');
});

it('protects content containing HTML-like text', function () {
    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
        <pre><code>const html = \'&lt;div class="example"&gt;Test&lt;/div&gt;\';</code></pre>
        <p>Example HTML: &lt;img src="example.jpg"&gt;</p>
    </body></html>';

    // Enable all protections
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
    ]);

    $result = $this->protector->protect($html);

    // Parse result HTML
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);

    // Extract text content
    $textContent = $dom->textContent;

    // HTML content should be preserved
    expect($textContent)->toContain('const html = \'<div class="example">Test</div>\';');
    expect($textContent)->toContain('Example HTML: <img src="example.jpg">');
});

it('preserves SVG content correctly', function () {
    // Enable text fragmentation
    config(['guardian.prevention.content_protection.text_fragmentation' => true]);

    $html = '<!DOCTYPE html><html><head><title>SVG Test</title></head><body>
        <p>Text before SVG</p>
        <svg width="100" height="100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="40" stroke="black" stroke-width="2" fill="red" />
            <text x="20" y="20">SVG Text</text>
        </svg>
        <p>Text after SVG</p>
    </body></html>';

    $result = $this->protector->protect($html);

    // SVG should be preserved intact
    expect($result)->toContain('<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">');
    expect($result)->toContain('<circle cx="50" cy="50" r="40" stroke="black" stroke-width="2" fill="red" />');
    expect($result)->toContain('<text x="20" y="20">SVG Text</text>');

    // Original text content should be preserved
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);
    $textContent = $dom->textContent;

    expect($textContent)->toContain('Text before SVG');
    expect($textContent)->toContain('SVG Text');
    expect($textContent)->toContain('Text after SVG');
});

it('preserves MathML content correctly', function () {
    // Enable text fragmentation
    config(['guardian.prevention.content_protection.text_fragmentation' => true]);

    $html = '<!DOCTYPE html><html><head><title>MathML Test</title></head><body>
        <p>Text before MathML</p>
        <math xmlns="http://www.w3.org/1998/Math/MathML">
            <mrow>
                <mi>x</mi>
                <mo>=</mo>
                <mfrac>
                    <mrow>
                        <mo>-</mo>
                        <mi>b</mi>
                        <mo>±</mo>
                        <msqrt>
                            <msup>
                                <mi>b</mi>
                                <mn>2</mn>
                            </msup>
                            <mo>-</mo>
                            <mn>4</mn>
                            <mi>a</mi>
                            <mi>c</mi>
                        </msqrt>
                    </mrow>
                    <mrow>
                        <mn>2</mn>
                        <mi>a</mi>
                    </mrow>
                </mfrac>
            </mrow>
        </math>
        <p>Text after MathML</p>
    </body></html>';

    $result = $this->protector->protect($html);

    // MathML should be preserved intact
    expect($result)->toContain('<math xmlns="http://www.w3.org/1998/Math/MathML">');
    expect($result)->toContain('<mfrac>');
    expect($result)->toContain('<msqrt>');

    // Original text content should be preserved
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);
    $textContent = $dom->textContent;

    expect($textContent)->toContain('Text before MathML');
    expect($textContent)->toContain('Text after MathML');
});

it('applies CSS layers protection when enabled', function () {
    // Enable CSS layers protection
    config(['guardian.prevention.content_protection.css_layers' => true]);

    $html = '<!DOCTYPE html><html><head><title>Layers Test</title></head><body>
        <article>
            <p>This is content in an article.</p>
        </article>
    </body></html>';

    $result = $this->protector->protect($html);

    // Should contain CSS layers
    expect($result)->toContain('@layer');
    expect($result)->toContain('gdn-layer-');
    expect($result)->toContain('gdn-content-');

    // Should contain the script to enable legitimate access
    expect($result)->toContain('enableLegitimateAccess');

    // Should add container class to content elements
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);

    $xpath = new \DOMXPath($dom);
    $elements = $xpath->query("//*[contains(@class, 'gdn-content-')]");

    expect($elements->length)->toBeGreaterThan(0);
});

it('skips CSS layers protection when disabled', function () {
    // Explicitly disable CSS layers protection
    config(['guardian.prevention.content_protection.css_layers' => false]);

    $html = '<!DOCTYPE html><html><head><title>Layers Test</title></head><body>
        <article>
            <p>This is content in an article.</p>
        </article>
    </body></html>';

    $result = $this->protector->protect($html);

    // Should not contain CSS layers
    expect($result)->not->toContain('@layer');
    expect($result)->not->toContain('gdn-layer-');
    expect($result)->not->toContain('enableLegitimateAccess');
});

it('handles documents with mixed SVG and MathML content', function () {
    // Enable text fragmentation
    config(['guardian.prevention.content_protection.text_fragmentation' => true]);

    $html = '<!DOCTYPE html><html><head><title>Mixed Content Test</title></head><body>
        <p>Regular text paragraph.</p>
        <svg width="100" height="100">
            <circle cx="50" cy="50" r="40" fill="red" />
        </svg>
        <p>Text between SVG and MathML.</p>
        <math>
            <mi>a</mi>
            <mo>+</mo>
            <mi>b</mi>
            <mo>=</mo>
            <mi>c</mi>
        </math>
        <p>Final paragraph.</p>
    </body></html>';

    $result = $this->protector->protect($html);

    // SVG should be preserved intact
    expect($result)->toContain('<svg width="100" height="100">');
    expect($result)->toContain('<circle cx="50" cy="50" r="40" fill="red" />');

    // MathML should be preserved intact
    expect($result)->toContain('<math>');
    expect($result)->toContain('<mi>a</mi>');

    // Regular paragraphs should be fragmented
    expect($result)->toContain('<span class="gdn-');

    // All content should be present
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);
    $textContent = $dom->textContent;

    expect($textContent)->toContain('Regular text paragraph');
    expect($textContent)->toContain('Text between SVG and MathML');
    expect($textContent)->toContain('Final paragraph');
});

it('handles deeply nested DOM structures efficiently', function () {
    // Create a deeply nested HTML structure
    $html = '<!DOCTYPE html><html><head><title>Nested Test</title></head><body><div>';

    // Create 10 levels of nesting
    for ($i = 1; $i <= 10; $i++) {
        $html .= "<div class='level-{$i}'>";
    }

    $html .= '<p>Deeply nested content</p>';

    // Close the nested divs
    for ($i = 1; $i <= 10; $i++) {
        $html .= '</div>';
    }

    $html .= '</div></body></html>';

    // Measure execution time
    $startTime = microtime(true);
    $result = $this->protector->protect($html);
    $endTime = microtime(true);

    // Should process in reasonable time (less than 1 second)
    expect($endTime - $startTime)->toBeLessThan(1.0);

    // Should preserve the nested structure
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);

    $xpath = new \DOMXPath($dom);
    $deepestElement = $xpath->query("//div[@class='level-10']");

    expect($deepestElement->length)->toBe(1);
    expect($dom->textContent)->toContain('Deeply nested content');
});

it('preserves script content without modification', function () {
    $html = '<!DOCTYPE html><html><head><title>Script Test</title></head><body>
        <p>Text paragraph</p>
        <script>
            // JavaScript with HTML-like content
            const template = \'<div class="test"><span>Test</span></div>\';
            if (x < 10 && y > 5) {
                console.log("Test & special chars < > &");
            }
        </script>
        <p>Final paragraph</p>
    </body></html>';

    $result = $this->protector->protect($html);

    // Script content should be preserved exactly
    expect($result)->toContain('const template = \'<div class="test"><span>Test</span></div>\';');
    expect($result)->toContain('if (x < 10 && y > 5) {');
    expect($result)->toContain('console.log("Test & special chars < > &");');

    // Original text content should be preserved
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);

    $xpath = new \DOMXPath($dom);
    $scripts = $xpath->query('//script');

    // Check if scripts exist
    expect($scripts->length)->toBeGreaterThanOrEqual(1);

    // Check text content is preserved
    expect($dom->textContent)->toContain('Text paragraph');
    expect($dom->textContent)->toContain('Final paragraph');
});

it('applies all protection techniques together correctly', function () {
    // Enable all protections
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
        'guardian.prevention.content_protection.add_honeytokens' => true,
        'guardian.prevention.content_protection.css_layers' => true,
    ]);

    $html = '<!DOCTYPE html><html><head><title>Test</title></head><body>
        <article>
            <h1>Test Article</h1>
            <p>This is a test paragraph that should be protected.</p>
        </article>
    </body></html>';

    $result = $this->protector->protect($html);

    // Should contain fragments
    expect($result)->toContain('<span class="gdn-');

    // Should contain CSS obfuscation
    expect($result)->toContain('direction: rtl');
    expect($result)->toContain('-container');

    // Should contain honeytokens
    expect($result)->toContain('guardian-honeypot');
    expect($result)->toContain('hidden-content');

    // Should contain CSS layers
    expect($result)->toContain('@layer');

    // Original content should be preserved
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);
    expect($dom->textContent)->toContain('Test Article');
    expect($dom->textContent)->toContain('This is a test paragraph that should be protected');
});
