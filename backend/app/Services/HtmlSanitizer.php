<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /** Allowed HTML tags for editorial content. */
    private const ALLOWED_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'a', 'img', 'code', 'pre',
        'figure', 'figcaption', 'hr', 'br', 'span', 'div',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /** Allowed attributes per tag or globally. */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'code' => ['class'],
        'pre' => ['class'],
        'blockquote' => ['cite'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
    ];

    /**
     * Sanitize rich HTML content:
     * - Strips dangerous tags (<script>, <style>, <iframe>, <object>, <embed>, <form>, <input>, etc.)
     * - Strips all inline event handlers (onclick, onload, onerror, etc.)
     * - Strips javascript:, data: (except images), and vbscript: URIs
     * - Ensures links have safe rel="noopener noreferrer"
     */
    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Suppress libxml errors for malformed HTML fragments
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new DOMDocument('1.0', 'UTF-8');
        // Wrap in a container to parse as UTF-8 HTML fragment
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="__sicksaint_root__">' . $html . '</div></body></html>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $root = $doc->getElementById('__sicksaint_root__');
        if ($root) {
            $this->cleanNode($root);
            $cleanHtml = '';
            foreach ($root->childNodes as $child) {
                $cleanHtml .= $doc->saveHTML($child);
            }
        } else {
            $cleanHtml = strip_tags($html, '<' . implode('><', self::ALLOWED_TAGS) . '>');
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return trim($cleanHtml);
    }

    private function cleanNode(DOMNode $node): void
    {
        // Process child nodes backwards so removals do not disrupt indexing
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);

                // Disallow blacklisted tags completely
                if (!in_array($tagName, self::ALLOWED_TAGS, true)) {
                    // Extract children to preserve text if safe, otherwise remove
                    if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'canvas'], true)) {
                        $node->removeChild($child);
                    } else {
                        // Unwrap tag but keep text/children
                        while ($child->hasChildNodes()) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                    }
                    continue;
                }

                // Clean attributes
                $allowedForTag = self::ALLOWED_ATTRIBUTES[$tagName] ?? [];
                for ($j = $child->attributes->length - 1; $j >= 0; $j--) {
                    $attr = $child->attributes->item($j);
                    $attrName = strtolower($attr->name);

                    // Disallow any inline event handler (starts with 'on')
                    if (str_starts_with($attrName, 'on') || !in_array($attrName, $allowedForTag, true)) {
                        $child->removeAttributeNode($attr);
                        continue;
                    }

                    // Validate URLs for href and src
                    if (in_array($attrName, ['href', 'src'], true)) {
                        $val = trim($attr->value);
                        // Prevent javascript:, vbscript:, and data: scripts
                        if (preg_match('/^(javascript|vbscript|data):/i', $val)) {
                            // Only allow data:image/ for images
                            if ($attrName === 'src' && preg_match('/^data:image\/(png|jpe?g|webp|gif);base64,/i', $val)) {
                                // allowed
                            } else {
                                $child->removeAttributeNode($attr);
                                continue;
                            }
                        }
                    }
                }

                // Add rel="noopener noreferrer" for external links
                if ($tagName === 'a') {
                    $href = $child->getAttribute('href');
                    if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                }

                // Recursively clean children
                $this->cleanNode($child);
            }
        }
    }
}
