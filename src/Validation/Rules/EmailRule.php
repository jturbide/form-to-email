<?php

declare(strict_types=1);

namespace FormToEmail\Validation\Rules;

use FormToEmail\Validation\Rule;

/**
 * Rule: EmailRule
 *
 * Validates that the given value is a properly formatted email address.
 * Uses PHP's built-in `filter_var()` with `FILTER_VALIDATE_EMAIL`.
 *
 * This rule does not check domain existence or MX records — it only
 * validates syntax according to RFC standards.
 *
 * Empty values are considered valid, so combine with `RequiredRule`
 * if the field must be present.
 *
 * Example:
 * ```php
 * $rule = new EmailRule();
 * $rule->validate('user@example.com'); // []
 * $rule->validate('invalid@@example'); // ['invalid_email']
 * ```
 */
final class EmailRule implements Rule
{
    public function __construct(
        /**
         * The error code returned when validation fails.
         */
        private readonly string $error = 'invalid_email'
    ) {
    }
    
    /**
     * @inheritDoc
     */
    #[\Override]
    public function validate(string $value): array
    {
        // Skip empty strings — RequiredRule should handle that
        if ($value === '') {
            return [];
        }
        
        // Validate syntax only
        return filter_var($value, FILTER_VALIDATE_EMAIL)
            ? []
            : [$this->error];
    }
}
