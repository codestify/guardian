<?php

namespace Shah\Guardian\Prevention\Protectors;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use Shah\Guardian\Prevention\Utilities\DomUtility;

class CssObfuscator
{
    /**
     * DOM utility instance.
     *
     * @var \Shah\Guardian\Prevention\Utilities\DomUtility
     */
    protected $domUtility;

    /**
     * Create a new CSS obfuscator instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->domUtility = new DomUtility;
    }

    /**
     * Apply CSS obfuscation to the DOM.
     *
     * This method applies direction and text alignment tricks to make content
     * appear normal to humans but confuse AI crawlers.
     */
    public function obfuscate(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        // Find content containers to protect
        $containers = $this->findContentContainers($xpath);
        if (empty($containers)) {
            return;
        }

        // Generate unique class names for protection
        $containerClass = 'gdn-'.$this->domUtility->generateUniqueClassName().'-container';
        $reverseClass = 'gdn-'.$this->domUtility->generateUniqueClassName().'-reverse';

        // Add classes to containers
        $this->addClassesToContainers($containers, $containerClass);

        // Add CSS style to the head
        $this->addObfuscationStyles($dom, $containerClass, $reverseClass);

        // Add reverse classes to elements inside containers
        $this->addReverseClasses($xpath, $containerClass, $reverseClass);
    }

    /**
     * Find content containers to protect.
     */
    protected function findContentContainers(DOMXPath $xpath): array
    {
        // First try to find semantic content containers
        $containers = $xpath->query('//article | //section | //div[contains(@class, "content")] | //div[contains(@class, "main")] | //main');

        // If no specific containers found, target paragraphs directly
        if ($containers->length === 0) {
            $containers = $xpath->query('//p[string-length(.) > 100]');
        }

        // Build container array
        $result = [];
        foreach ($containers as $container) {
            if ($container instanceof DOMElement) {
                $result[] = $container;
            }
        }

        return $result;
    }

    /**
     * Add classes to content containers.
     */
    protected function addClassesToContainers(array $containers, string $containerClass): void
    {
        foreach ($containers as $container) {
            // Skip containers in nav, header, or footer
            if ($this->shouldSkipContainer($container)) {
                continue;
            }

            // Add our protection class
            $classes = $container->getAttribute('class');
            $container->setAttribute('class', trim($classes.' '.$containerClass));
        }
    }

    /**
     * Determine if a container should be skipped (e.g., if it's in navigation).
     */
    protected function shouldSkipContainer(DOMElement $container): bool
    {
        $skipNodes = ['nav', 'header', 'footer', 'menu'];
        $skipClasses = ['nav', 'navbar', 'header', 'footer', 'menu', 'navigation'];

        // Check if this element or any parent has skip classes or is a skip element
        $parent = $container;

        while ($parent instanceof DOMElement) {
            // Check node name
            if (in_array(strtolower($parent->nodeName), $skipNodes)) {
                return true;
            }

            // Check class attributes
            $classes = $parent->getAttribute('class');
            foreach ($skipClasses as $skipClass) {
                if (Str::contains($classes, $skipClass)) {
                    return true;
                }
            }

            // Move up the tree
            $parent = $parent->parentNode;
        }

        return false;
    }

    /**
     * Add obfuscation styles to the document.
     */
    protected function addObfuscationStyles(DOMDocument $dom, string $containerClass, string $reverseClass): void
    {
        // Create CSS style element
        $style = $dom->createElement('style');
        $style->setAttribute('type', 'text/css');

        // CSS that makes content look normal to humans but confuses scrapers
        $css = "
            /* Guardian content protection styles */
            .{$containerClass} {
                direction: rtl;
                text-align: left;
            }
            .{$containerClass} * {
                direction: rtl;
                text-align: left;
                unicode-bidi: bidi-override;
            }
            .{$containerClass} .{$reverseClass} {
                direction: ltr;
                unicode-bidi: bidi-override;
            }
        ";

        $style->appendChild($dom->createTextNode($css));

        // Add the style to head
        $head = $this->domUtility->ensureHead($dom);
        $head->appendChild($style);
    }

    /**
     * Add reverse classes to elements inside protected containers.
     */
    protected function addReverseClasses(DOMXPath $xpath, string $containerClass, string $reverseClass): void
    {
        // Query for specific elements inside container that should have reverse class
        $query = implode(' | ', array_map(
            fn ($tag) => "//*[contains(@class, '{$containerClass}')]//{$tag}",
            ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'figcaption', 'blockquote']
        ));

        $elementsToReverse = $xpath->query($query);

        foreach ($elementsToReverse as $element) {
            if ($element instanceof DOMElement) {
                $classes = $element->getAttribute('class');
                $element->setAttribute('class', trim($classes.' '.$reverseClass));
            }
        }
    }
}
