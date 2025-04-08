<?php

namespace Shah\Guardian\Prevention\Utilities;

class ElementPreserver
{
    /**
     * Preserve special elements like SVG and MathML before DOM processing.
     */
    public function preserveSpecialElements(string $content, array &$preservedElements): string
    {
        // Elements to preserve
        $specialElements = [
            'svg' => ['<svg', '</svg>'],
            'math' => ['<math', '</math>'],
            'script' => ['<script', '</script>'],
            'style' => ['<style', '</style>'],
            'noscript' => ['<noscript', '</noscript>'],
            'template' => ['<template', '</template>'],
        ];

        // Process each special element type
        foreach ($specialElements as $type => $markers) {
            $openTag = $markers[0];
            $closeTag = $markers[1];

            // Find positions of all instances of this element type
            $startPos = 0;
            while (($startPos = stripos($content, $openTag, $startPos)) !== false) {
                // Find the matching closing tag
                $endPos = stripos($content, $closeTag, $startPos);
                if ($endPos === false) {
                    $startPos += strlen($openTag); // Skip this occurrence if no closing tag

                    continue;
                }

                // Extract the full element with tags
                $endPos += strlen($closeTag);
                $fullElement = substr($content, $startPos, $endPos - $startPos);

                // Create unique placeholder
                $placeholder = "<!--{$type}_".md5($fullElement.microtime(true)).'-->';
                $preservedElements[$placeholder] = $fullElement;

                // Replace content with placeholder
                $content = substr_replace($content, $placeholder, $startPos, $endPos - $startPos);

                // Reset search position
                $startPos = $startPos + strlen($placeholder);
            }
        }

        return $content;
    }

    /**
     * Restore preserved elements after DOM processing.
     */
    public function restorePreservedElements(string $content, array $preservedElements): string
    {
        // Sort placeholders by length descending to avoid partial replacements
        $placeholders = array_keys($preservedElements);
        usort($placeholders, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        // Replace each placeholder with its original content
        foreach ($placeholders as $placeholder) {
            $content = str_replace($placeholder, $preservedElements[$placeholder], $content);
        }

        return $content;
    }
}
