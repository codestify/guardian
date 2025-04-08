<?php

namespace Shah\Guardian\Tests\Unit;

use Shah\Guardian\Prevention\ContentProtector;

beforeEach(function () {
    $this->protector = new ContentProtector;
});

it('handles extremely large DOM structures', function () {
    // Create a deeply nested HTML structure
    $html = '<!DOCTYPE html><html lang="en"><head><title>Nested Test</title></head><body>';

    // Create a deeply nested structure (10 levels deep)
    $currentHtml = '<div class="level-10"><p>Deepest level</p></div>';
    for ($i = 9; $i >= 1; $i--) {
        $currentHtml = "<div class=\"level-{$i}\">{$currentHtml}</div>";
    }

    $html .= $currentHtml.'</body></html>';

    // Enable all protections
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
        'guardian.prevention.content_protection.add_honeytokens' => true,
    ]);

    // Should not cause stack overflow or timeout
    $result = $this->protector->protect($html);

    // Result should be valid HTML
    $dom = new \DOMDocument;
    $parseResult = @$dom->loadHTML($result);
    expect($parseResult)->toBeTrue();

    // Should still contain the deepest level text
    expect($result)->toContain('Deepest level');
});

it('handles pages with SVG content', function () {
    $html = '<!DOCTYPE html><html><head><title>SVG Test</title></head><body>
        <p>Text before SVG</p>
        <svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="40" stroke="black" stroke-width="2" fill="red" />
            <text x="20" y="20">SVG Text</text>
        </svg>
        <p>Text after SVG</p>
    </body></html>';

    // Enable text fragmentation
    config(['guardian.prevention.content_protection.text_fragmentation' => true]);

    $result = $this->protector->protect($html);

    // Should not break SVG content
    expect($result)->toContain('<svg');
    expect($result)->toContain('<circle');
    expect($result)->toContain('<text');

    // Result should be valid HTML
    $dom = new \DOMDocument;
    $parseResult = @$dom->loadHTML($result);
    expect($parseResult)->toBeTrue();

    // Should preserve all text content
    expect($dom->textContent)->toContain('Text before SVG');
    expect($dom->textContent)->toContain('SVG Text');
    expect($dom->textContent)->toContain('Text after SVG');
});

it('handles pages with MathML content', function () {
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

    // Enable text fragmentation
    config(['guardian.prevention.content_protection.text_fragmentation' => true]);

    $result = $this->protector->protect($html);

    // Should not break MathML content
    expect($result)->toContain('<math');
    expect($result)->toContain('<mrow');
    expect($result)->toContain('<mfrac');

    // Result should be valid HTML
    $dom = new \DOMDocument;
    $parseResult = @$dom->loadHTML($result);
    expect($parseResult)->toBeTrue();

    // Text content should be preserved
    expect($dom->textContent)->toContain('Text before MathML');
    expect($dom->textContent)->toContain('Text after MathML');
});

it('handles pages with multiple script and style tags', function () {
    $html = '<!DOCTYPE html><html><head>
        <title>Multiple Scripts Test</title>
        <style>body { font-family: Arial,serif; }</style>
        <script>console.log("Script 1");</script>
        <style>.highlight { color: red; }</style>
        <script>
            // Multi-line script with HTML-like content
            let template = "<div class=\"test\">Test</div>";
            if (x < 10 && y > 5) {
                console.log("Test");
            }
        </script>
    </head><body>
        <p>Test content</p>
        <script>console.log("Inline script");</script>
        <style>p { margin: 10px; }</style>
    </body></html>';

    // Enable all protections
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
        'guardian.prevention.content_protection.add_honeytokens' => true,
    ]);

    $result = $this->protector->protect($html);

    // Should preserve all script and style tags
    $dom = new \DOMDocument;
    @$dom->loadHTML($result);

    $scripts = $dom->getElementsByTagName('script');
    $styles = $dom->getElementsByTagName('style');

    // Original + guardian script
    expect($scripts->length)->toBeGreaterThanOrEqual(4);

    // Original + guardian style
    expect($styles->length)->toBeGreaterThanOrEqual(4);

    // Script content should be preserved
    $scriptContent = $result;
    expect($scriptContent)->toContain('console.log("Script 1")')
        ->and($scriptContent)->toContain('let template = "<div class=\"test\">Test</div>"')
        ->and($scriptContent)->toContain('if (x < 10 && y > 5)')
        ->and($scriptContent)->toContain('console.log("Inline script")')
        ->and($scriptContent)->toContain('body { font-family: Arial; }')
        ->and($scriptContent)->toContain('.highlight { color: red; }')
        ->and($scriptContent)->toContain('p { margin: 10px; }');

    // Style content should be preserved
});

it('handles pages with custom elements', function () {
    $html = '<!DOCTYPE html><html><head><title>Custom Elements Test</title></head><body>
        <p>Text before custom element</p>
        <my-component data-value="test">
            <inner-element>Inner content</inner-element>
        </my-component>
        <p>Text after custom element</p>
    </body></html>';

    // Enable all protections
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
        'guardian.prevention.content_protection.add_honeytokens' => true,
    ]);

    $result = $this->protector->protect($html);

    // Should preserve custom element tags and attributes
    expect($result)->toContain('<my-component');
    expect($result)->toContain('data-value="test"');
    expect($result)->toContain('<inner-element');

    // Regular HTML text should be protected
    expect($result)->toContain('<span class="gdn-');

    // Result should be valid HTML
    $dom = new \DOMDocument;
    $parseResult = @$dom->loadHTML($result);
    expect($parseResult)->toBeTrue();

    // Text content should be preserved
    expect($dom->textContent)->toContain('Text before custom element');
    expect($dom->textContent)->toContain('Inner content');
    expect($dom->textContent)->toContain('Text after custom element');
});

it('handles pages with non-standard HTML5 attributes', function () {
    $html = '<!DOCTYPE html><html><head><title>Custom Attributes Test</title></head><body>
        <div data-test="value" aria-label="Test" role="button">Standard HTML5 attributes</div>
        <div ng-app="myApp" ng-controller="myCtrl" v-for="item in items" x-data="{open: false}">
            Framework attributes
        </div>
        <div custom-attr="test" another-attr="value">Custom attributes</div>
    </body></html>';

    // Enable all protections
    config([
        'guardian.prevention.content_protection.text_fragmentation' => true,
        'guardian.prevention.content_protection.css_obfuscation' => true,
        'guardian.prevention.content_protection.add_honeytokens' => true,
    ]);

    $result = $this->protector->protect($html);

    // Should preserve custom attributes
    expect($result)->toContain('data-test="value"')
        ->and($result)->toContain('aria-label="Test"')
        ->and($result)->toContain('role="button"')
        ->and($result)->toContain('ng-app="myApp"')
        ->and($result)->toContain('ng-controller="myCtrl"')
        ->and($result)->toContain('v-for="item in items"')
        ->and($result)->toContain('x-data="{open: false}"')
        ->and($result)->toContain('custom-attr="test"')
        ->and($result)->toContain('another-attr="value"');

    // Result should be valid HTML
    $dom = new \DOMDocument;
    $parseResult = @$dom->loadHTML($result);
    expect($parseResult)->toBeTrue();
});

it('handles empty HTML documents', function () {
    $emptyHtmlCases = [
        '<!DOCTYPE html><html></html>',
        '<!DOCTYPE html><html><head></head><body></body></html>',
        '<html><body></body></html>',
    ];

    foreach ($emptyHtmlCases as $html) {
        // Enable all protections
        config([
            'guardian.prevention.content_protection.text_fragmentation' => true,
            'guardian.prevention.content_protection.css_obfuscation' => true,
            'guardian.prevention.content_protection.add_honeytokens' => true,
        ]);

        $result = $this->protector->protect($html);

        // Should still add guardian metadata
        expect($result)->toContain('<meta name="guardian-protected"');

        // Should still add protection script
        expect($result)->toContain('<script');

        // Should be valid HTML
        $dom = new \DOMDocument;
        $parseResult = @$dom->loadHTML($result);
        expect($parseResult)->toBeTrue();
    }
});
