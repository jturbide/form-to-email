<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;
use Normalizer;

/**
 * Class RemoveUrlFilter
 *
 * Removes or replaces URLs, domains, and disguised web links from user input.
 * Prevents spam, phishing, or unwanted clickable links in form messages.
 *
 * Default (aggressive) mode catches:
 * - Standard URLs (http, https, www)
 * - Bare domains (example.com)
 * - Obfuscated domains (example[.]com, hxxp://)
 * - Unicode homoglyphs (ｅxample․com)
 *
 * Email addresses (user@example.com) are preserved.
 */
final class RemoveUrlFilter extends AbstractFilter
{
    public function __construct(
        private readonly bool $replaceWithPlaceholder = false,
        private readonly string $placeholder = '[link removed]',
        private readonly bool $aggressive = true,
    ) {
    }
    
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // Normalize Unicode (NFKC) — converts Ｅ → E, ． → .
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_KC);
        }
        
        // In aggressive mode, replace common Unicode "dot" characters too
        if ($this->aggressive) {
            $value = str_replace(["․", "。", "．"], ".", $value);
            
            // Replace obfuscated separators like [dot] or (dot)
            $value = preg_replace('/\s*(?:\[\.]|\(dot\)|\s+dot\s+)\s*/iu', '.', $value);
            
            // Convert hxxp:// spam to http://
            $value = preg_replace('/\bhxxp(s)?:\/\//iu', 'http$1://', $value ?? '');
        }
        
        // Regex for standard URLs/domains — excludes emails
        $pattern = '/\b(?:(?:https?:\/\/|www\.)[^\s<]+|(?<!@)[a-z0-9.-]+\.[a-z]{2,})(?:\/[^\s<]*)?/iu';
        
        $cleaned = preg_replace($pattern, $this->replaceWithPlaceholder ? $this->placeholder : '', $value ?? '');
        return preg_replace('/\s{2,}/', ' ', trim($cleaned ?? ''));
    }
}
