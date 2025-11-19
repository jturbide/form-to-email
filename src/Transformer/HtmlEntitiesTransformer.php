<?php

declare(strict_types=1);

namespace FormToEmail\Transformer;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;

/**
 * HTML entity escaping transformer.
 *
 * Prevents any HTML from passing through to emails, logs, or templates.
 *
 * Features:
 * - Configurable flags (ENT_QUOTES, ENT_HTML5, ENT_SUBSTITUTE, etc.)
 * - Configurable encoding (default UTF-8)
 * - Configurable double-encoding prevention
 * - Skips non-string values
 */
final class HtmlEntitiesTransformer extends AbstractTransformer
{
    public function __construct(
        private readonly int $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401,
        private readonly string $encoding = 'UTF-8',
        private readonly bool $doubleEncode = false
    ) {
    }
    
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field, FormContext $context): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }
        
        return htmlentities(
            string: $value,
            flags:  $this->flags,
            encoding: $this->encoding,
            double_encode: $this->doubleEncode
        );
    }
}
