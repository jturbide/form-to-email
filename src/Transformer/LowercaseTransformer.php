<?php

declare(strict_types=1);

namespace FormToEmail\Transformer;

use FormToEmail\Core\FieldDefinition;

/**
 * Converts string values to lowercase.
 *
 * Features:
 * - Unicode-aware by default (uses mb_strtolower)
 * - Option to disable Unicode mode for legacy or performance
 * - Skips non-string values
 */
class LowercaseTransformer extends AbstractTransformer
{
    public function __construct(
        private readonly bool $unicodeAware = true
    ) {}
    
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        return $this->unicodeAware
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
