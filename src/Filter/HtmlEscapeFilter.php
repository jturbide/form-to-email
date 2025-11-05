<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * Class HtmlEscapeFilter
 *
 * Escapes HTML-sensitive characters into entities.
 * Converts &, <, >, ", and ' to their corresponding HTML codes.
 *
 * Features:
 * - Configurable HTML entity mode (HTML5, HTML4, XML)
 * - Optional prevention of double-encoding
 * - Supports custom charset
 */
class HtmlEscapeFilter extends AbstractFilter
{
    public function __construct(
        private readonly int $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
        private readonly string $encoding = 'UTF-8',
        private readonly bool $doubleEncode = true,
    ) {}
    
    /**
     * @inheritDoc
     */
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        return htmlspecialchars($value, $this->flags, $this->encoding, $this->doubleEncode);
    }
}
