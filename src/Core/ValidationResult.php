<?php

declare(strict_types=1);

namespace FormToEmail\Core;

/**
 * Class: ValidationResult
 *
 * Represents the outcome of form validation.
 * This value object is immutable and contains three components:
 *
 * - `valid`: overall boolean success flag.
 * - `errors`: associative array of validation errors.
 * - `data`: associative array of sanitized values.
 *
 * The `errors` array maps field names to a list of error codes.
 * This structure allows frontends to handle multi-error feedback
 * and translate messages according to their own locale or UX rules.
 *
 * Example:
 * ```php
 * new ValidationResult(
 *     valid: false,
 *     errors: [
 *         'email' => ['required', 'invalid_email'],
 *         'message' => ['too_short']
 *     ],
 *     data: [
 *         'email' => '',
 *         'message' => 'Hi'
 *     ]
 * );
 * ```
 */
final readonly class ValidationResult
{
    /**
     * @param bool $valid
     *   Indicates whether validation passed.
     *
     * @param array<string, list<string>> $errors
     *   Field-specific error codes.
     *
     * @param array<string, string> $data
     *   Sanitized or normalized field values.
     */
    public function __construct(
        public bool $valid,
        public array $errors,
        public array $data,
    ) {
    }
    
    /**
     * Convenience accessor for checking if validation failed.
     */
    public function failed(): bool
    {
        return !$this->valid;
    }
    
    /**
     * Returns true if a specific field has validation errors.
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }
    
    /**
     * Returns all error codes for a given field, or an empty list.
     *
     * @return list<string>
     */
    public function getErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
