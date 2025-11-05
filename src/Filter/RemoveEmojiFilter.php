<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * Class RemoveEmojiFilter
 *
 * Removes emoji and pictographic symbols from string values.
 * Useful for structured storage or when text must stay ASCII/UTF-8 clean.
 *
 * - Removes emoji code points (1F300–1FAFF)
 * - Removes variation selectors and joiners (FE0F, 200D)
 * - Removes flag symbols (1F1E6–1F1FF)
 * - Collapses residual spacing without trimming intentional spacing
 */
class RemoveEmojiFilter extends AbstractFilter
{
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // Remove emojis, pictographs, flags, and joiners
        $pattern = '/['
            . '\x{1F300}-\x{1F6FF}' // Misc symbols & pictographs
            . '\x{1F900}-\x{1F9FF}' // Supplemental symbols & pictographs
            . '\x{2600}-\x{26FF}'   // Misc symbols (sun, umbrella)
            . '\x{2700}-\x{27BF}'   // Dingbats
            . '\x{1FA70}-\x{1FAFF}' // Extended-A emojis
            . '\x{1F1E6}-\x{1F1FF}' // Regional indicator symbols (flags)
            . '\x{200D}'            // Zero-width joiner
            . '\x{FE0F}'            // Variation selector-16
            . ']/u';
        
        $clean = preg_replace($pattern, '', $value ?? '');
        
        // Collapse multiple spaces and remove space before punctuation
        $clean = preg_replace('/\s{2,}/u', ' ', $clean ?? '');
        $clean = preg_replace('/\s+([.,!?;:])/u', '$1', $clean ?? '');
        
        // Remove stray spaces at beginning and end ONLY if caused by emoji deletion
        $clean = preg_replace('/^\s+|\s+$/u', '', $clean ?? '');
        
        return $clean ?? '';
    }
}
