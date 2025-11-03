<?php

declare(strict_types = 1);

namespace FormToEmail\Validation\Rules;

use FormToEmail\Validation\Rule;

/**
 * Rule: RegexRule
 *
 * Validates that an input value matches a specific regular
 * expression pattern.
 *
 * Useful for pattern-based validation such as:
 * - Letters only (`/^[A-Za-z]+$/`)
 * - Phone numbers (`/^\+?[0-9\s\-\(\)]+$/`)
 * - Postal codes, usernames, etc.
 *
 * This rule ignores empty strings — allowing you to combine
 * it with a `RequiredRule` for optional fields.
 *
 * Example:
 * ```php
 * $rule = new RegexRule('/^[A-Za-z]+$/', 'invalid_letters');
 * $rule->validate('John'); // []
 * $rule->validate('123');  // ['invalid_letters']
 * ```
 */
final class RegexRule implements Rule
{
    public function __construct(
        /**
         * Regular expression pattern (delimiters included).
         * Example: `/^[A-Za-z]+$/u`
         */
        private readonly string $pattern,
        
        /**
         * Error code to return if the pattern does not match.
         */
        private readonly string $error = 'invalid_format',
    ) {}
    
    /**
     * @inheritDoc
     */
    #[\Override]
    public function validate(string $value): array
    {
        // Empty string should be handled by RequiredRule if needed
        if ($value === '') {
            return [];
        }
        
        if ($this->pattern === '') {
            return [];
        }
        
        // If the regex fails or errors out, return the error code
        $result = @preg_match($this->pattern, $value);
        return $result === 1 ? [] : [$this->error];
    }
}
