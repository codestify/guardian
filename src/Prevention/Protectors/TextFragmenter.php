<?php

namespace Shah\Guardian\Prevention\Protectors;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Shah\Guardian\Prevention\Utilities\DomUtility;

class TextFragmenter
{
    /**
     * DOM utility instance.
     *
     * @var \Shah\Guardian\Prevention\Utilities\DomUtility
     */
    protected $domUtility;

    /**
     * Create a new text fragmenter instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->domUtility = new DomUtility;
    }

    /**
     * Fragment text content in the DOM.
     */
    public function fragmentText(DOMDocument $dom): void
    {
        // Process in batches to improve performance with large DOMs
        $body = $dom->getElementsByTagName('body')->item(0);

        if (! $body) {
            return;
        }

        // For the body, use XPath to directly find text nodes that need processing
        $xpath = new DOMXPath($dom);
        $textNodes = $xpath->query('.//text()[string-length(normalize-space(.)) > 20]', $body);

        if ($textNodes && $textNodes->length > 0) {
            $nodesToProcess = [];

            // Create a list of nodes to process that aren't in protected elements
            foreach ($textNodes as $textNode) {
                if (! $this->isInProtectedElement($textNode) && ! ctype_space($textNode->nodeValue)) {
                    $nodesToProcess[] = $textNode;
                }
            }

            // Process nodes in reverse order to avoid position changes affecting iteration
            for ($i = count($nodesToProcess) - 1; $i >= 0; $i--) {
                $this->fragmentTextNode($nodesToProcess[$i]);
            }
        }
    }

    /**
     * Fragment a specific text node into spans with unique class names.
     */
    protected function fragmentTextNode(DOMText $node): void
    {
        $text = $node->nodeValue;

        // Only fragment reasonably long text nodes with actual content
        if (strlen(trim($text)) > 20 && ! ctype_space($text)) {
            // Extract meaningful fragments
            $fragments = $this->splitTextIntoFragments($text);
            $fragment = $node->ownerDocument->createDocumentFragment();

            foreach ($fragments as $i => $part) {
                if ($i > 0) {
                    $fragment->appendChild($node->ownerDocument->createTextNode(' '));
                }

                // Create span with unique class for each fragment
                $span = $node->ownerDocument->createElement('span');
                $className = 'gdn-'.$this->domUtility->generateUniqueClassName();
                $span->setAttribute('class', $className);
                $span->textContent = $part;
                $fragment->appendChild($span);
            }

            if ($node->parentNode) {
                $node->parentNode->replaceChild($fragment, $node);
            }
        }
    }

    /**
     * Determine if a node is in a protected element that shouldn't be modified.
     */
    protected function isInProtectedElement(DOMNode $node): bool
    {
        // List of HTML elements whose contents should not be modified
        $protectedElements = [
            'script',
            'style',
            'noscript',
            'code',
            'pre',
            'iframe',
            'textarea',
            'template',
            'xmp',
            'title',
            'option',
        ];

        // Special namespace elements that should be preserved
        $protectedNamespaces = [
            'http://www.w3.org/2000/svg',
            'http://www.w3.org/1998/Math/MathML',
        ];

        // Always protect comments, CDATA and processing instructions
        if (
            $node->nodeType === XML_COMMENT_NODE ||
            $node->nodeType === XML_CDATA_SECTION_NODE ||
            $node->nodeType === XML_PI_NODE
        ) {
            return true;
        }

        $current = $node->parentNode;

        while ($current instanceof DOMElement) {
            // Check for elements in special namespaces (SVG/MathML)
            if ($current->namespaceURI && in_array($current->namespaceURI, $protectedNamespaces)) {
                return true;
            }

            // Check standard protected elements
            if (in_array(strtolower($current->nodeName), $protectedElements)) {
                return true;
            }

            // Also check for SVG and MathML elements by name
            if (in_array(strtolower($current->nodeName), ['svg', 'math'])) {
                return true;
            }

            // Check for elements with custom tags that might be framework components
            if (strpos($current->nodeName, '-') !== false) {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
    }

    /**
     * Split text into fragments for protection.
     */
    protected function splitTextIntoFragments(string $text): array
    {
        $words = preg_split('/\s+/', $text);
        $fragments = [];
        $currentFragment = '';

        foreach ($words as $word) {
            if (strlen($currentFragment) + strlen($word) + 1 <= 20) {
                $currentFragment .= ($currentFragment ? ' ' : '').$word;
            } else {
                if ($currentFragment) {
                    $fragments[] = $currentFragment;
                }
                $currentFragment = $word;
            }
        }

        if ($currentFragment) {
            $fragments[] = $currentFragment;
        }

        return $fragments;
    }
}
