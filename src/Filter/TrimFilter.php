<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * Removes leading and trailing whitespace from string values.
 * Supports Unicode-aware trimming and direction modes (left, right, both).
 */
class TrimFilter extends AbstractFilter
{
    private const UNICODE_PATTERNS = [
        'both' => '/^[\p{Z}\p{C}]+|[\p{Z}\p{C}]+$/u',
        'left' => '/^[\p{Z}\p{C}]+/u',
        'right' => '/[\p{Z}\p{C}]+$/u',
    ];
    
    private const ASCII_MASK = " \n\r\t\v\0";
    
    public function __construct(
        private readonly bool $unicodeAware = true,
        private readonly string $mode = 'both', // 'left', 'right', or 'both'
    ) {
        if (!in_array($this->mode, ['left', 'right', 'both'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid mode "%s". Must be one of: left, right, both.',
                $this->mode
            ));
        }
    }
    
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        if ($this->unicodeAware) {
            return preg_replace(self::UNICODE_PATTERNS[$this->mode], '', $value);
        }
        
        return match ($this->mode) {
            'left' => ltrim($value, self::ASCII_MASK),
            'right' => rtrim($value, self::ASCII_MASK),
            default => trim($value, self::ASCII_MASK),
        };
    }
}
