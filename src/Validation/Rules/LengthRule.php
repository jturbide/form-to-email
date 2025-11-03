<?php

declare(strict_types = 1);

namespace FormToEmail\Validation\Rules;

use FormToEmail\Validation\Rule;

/**
 * Rule: LengthRule
 *
 * Ensures that the given string value respects a minimum and/or
 * maximum character length.
 *
 * It uses `mb_strlen()` for multibyte (UTF-8) safety.
 * Empty strings are considered valid — combine this with
 * `RequiredRule` for mandatory fields.
 *
 * Example:
 * ```php
 * $rule = new LengthRule(min: 5, max: 100);
 * $rule->validate('abc');  // ['too_short']
 * $rule->validate(str_repeat('a', 120)); // ['too_long']
 * $rule->validate('hello'); // []
 * ```
 */
final class LengthRule implements Rule
{
    public function __construct(
        /**
         * Minimum allowed length (null = no minimum).
         */
        private readonly ?int $min = null,
        
        /**
         * Maximum allowed length (null = no maximum).
         */
        private readonly ?int $max = null,
        
        /**
         * Error code when value is shorter than $min.
         */
        private readonly string $tooShort = 'too_short',
        
        /**
         * Error code when value is longer than $max.
         */
        private readonly string $tooLong = 'too_long',
    ) {}
    
    /**
     * @inheritDoc
     */
    public function validate(string $value): array
    {
        // Allow empty strings (handled by RequiredRule if needed)
        if ($value === '') {
            return [];
        }
        
        $length = mb_strlen($value);
        $errors = [];
        
        if ($this->min !== null && $length < $this->min) {
            $errors[] = $this->tooShort;
        }
        
        if ($this->max !== null && $length > $this->max) {
            $errors[] = $this->tooLong;
        }
        
        return $errors;
    }
}
