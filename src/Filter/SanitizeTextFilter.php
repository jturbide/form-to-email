<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * Class SanitizeTextFilter
 *
 * Removes invalid UTF-8 sequences, invisible control characters,
 * and non-printable symbols from user input.
 *
 * Ideal as a last-pass safety filter for any text field.
 *
 * Options:
 * - removeReplacementChar: removes U+FFFD ("�") characters that may appear when
 *   previous conversions replaced invalid bytes (enabled by default).
 */
class SanitizeTextFilter extends AbstractFilter
{
    public function __construct(
        private readonly bool $removeReplacementChar = true
    ) {}
    
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // 1. Ensure UTF-8 encoding is valid (ignore invalid sequences)
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        
        // If iconv fails completely, fall back to original string
        if ($clean === false) {
            $clean = $value;
        }
        
        // 2. Remove control chars except line breaks & tabs
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean);
        if ($clean === null) {
            $clean = $value;
        }
        
        // 3. Strict mode: remove U+FFFD (replacement character)
        if ($this->removeReplacementChar) {
            $clean = str_replace("\u{FFFD}", '', $clean);
        }
        
        // 4. Trim trailing whitespace and newlines
        return trim($clean);
    }
}
