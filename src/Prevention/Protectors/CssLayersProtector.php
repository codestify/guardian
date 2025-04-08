<?php

namespace Shah\Guardian\Prevention\Protectors;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Shah\Guardian\Prevention\Utilities\DomUtility;

class CssLayersProtector
{
    /**
     * DOM utility instance.
     *
     * @var \Shah\Guardian\Prevention\Utilities\DomUtility
     */
    protected $domUtility;

    /**
     * Create a new CSS layers protector instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->domUtility = new DomUtility;
    }

    /**
     * Apply CSS layers protection to the DOM.
     * This uses modern CSS features to make content scraping more difficult.
     */
    public function apply(DOMDocument $dom): void
    {
        // Check if CSS layers are enabled in config
        if (! config('guardian.prevention.content_protection.css_layers', false)) {
            return;
        }

        $xpath = new DOMXPath($dom);
        $head = $xpath->query('//head')->item(0);

        if (! $head) {
            return;
        }

        // Generate unique layer name
        $layerName = 'gdn-layer-'.$this->domUtility->generateUniqueClassName();
        $containerClass = 'gdn-content-'.$this->domUtility->generateUniqueClassName();

        // Create style element for layers
        $layerStyle = $dom->createElement('style');
        $layerStyle->setAttribute('type', 'text/css');

        // Create CSS that uses layers to protect content
        $css = "
            /* Guardian layer-based protection */
            @layer {$layerName}, base;

            @layer base {
                .{$containerClass} p,
                .{$containerClass} h1,
                .{$containerClass} h2,
                .{$containerClass} h3,
                .{$containerClass} h4,
                .{$containerClass} li {
                    opacity: 0.01;
                    user-select: none;
                }
            }

            @layer {$layerName} {
                .{$containerClass} p,
                .{$containerClass} h1,
                .{$containerClass} h2,
                .{$containerClass} h3,
                .{$containerClass} h4,
                .{$containerClass} li {
                    opacity: 1;
                    user-select: text;
                }
            }
        ";

        $layerStyle->appendChild($dom->createTextNode($css));
        $head->appendChild($layerStyle);

        // Add script to dynamically enable the protection when suspicious behavior is detected
        $script = $dom->createElement('script');
        $script->setAttribute('type', 'text/javascript');

        $scriptContent = "
            /* Guardian layer protection script */
            (function() {
                // Function to disable content copy protection for real users
                function enableLegitimateAccess() {
                    document.querySelectorAll('.{$containerClass}').forEach(function(el) {
                        el.style.setProperty('will-change', 'opacity', 'important');
                    });
                }

                // Enable legitimate access for real human visitors
                let humanInteractions = 0;

                // Track human-like behaviors
                ['mousemove', 'click', 'scroll', 'keydown'].forEach(function(event) {
                    document.addEventListener(event, function(e) {
                        if (humanInteractions < 3) {
                            humanInteractions++;
                            if (humanInteractions >= 2) {
                                enableLegitimateAccess();
                            }
                        }
                    }, {once: false, passive: true});
                });

                // Delayed activation for legitimate users
                setTimeout(enableLegitimateAccess, 800);
            })();
        ";

        $script->appendChild($dom->createTextNode($scriptContent));
        $head->appendChild($script);

        // Add the container class to main content elements
        $contentElements = $xpath->query('//article | //main | //section | //div[contains(@class, "content")]');

        if ($contentElements->length === 0) {
            // If no typical content containers, apply to body
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body) {
                $classes = $body->getAttribute('class');
                $body->setAttribute('class', trim($classes.' '.$containerClass));
            }
        } else {
            foreach ($contentElements as $element) {
                if ($element instanceof DOMElement) {
                    $classes = $element->getAttribute('class');
                    $element->setAttribute('class', trim($classes.' '.$containerClass));
                }
            }
        }
    }
}
