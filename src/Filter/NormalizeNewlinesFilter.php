<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * Class NormalizeNewlinesFilter
 *
 * Normalizes all newline sequences in a string to "\n".
 * Converts CRLF ("\r\n") and CR ("\r") to LF ("\n").
 *
 * Features:
 * - Optionally enforces a single trailing newline
 * - Ensures consistent line endings across OSes
 * - Safe for plain text, markdown, or email templates
 */
class NormalizeNewlinesFilter extends AbstractFilter
{
    public function __construct(
        private readonly bool $ensureTrailingNewline = true
    ) {}
    
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // Replace CRLF and CR with LF
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        
        // Trim excessive trailing newlines (more than one)
        $normalized = preg_replace('/\n{2,}$/', "\n", $normalized ?? '') ?? '';
        
        // Optionally ensure exactly one trailing newline
        if ($this->ensureTrailingNewline && !str_ends_with($normalized, "\n")) {
            $normalized .= "\n";
        }
        
        return $normalized;
    }
}
