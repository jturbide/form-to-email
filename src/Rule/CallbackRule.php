<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\FieldDefinition;

/**
 * Rule: CallbackRule
 *
 * Provides the most flexible validation mechanism by allowing
 * developers to define custom logic using a PHP callable or closure.
 *
 * The callback receives the input value and must return a list
 * of error codes (strings). Returning an empty list indicates success.
 *
 * This rule can be used for:
 * - Complex validation (e.g., domain-specific formats)
 * - External checks (e.g., verifying tokens or captchas)
 * - Conditional validation (based on environment or context)
 *
 * Example:
 * ```php
 * $rule = new CallbackRule(static function (string $value): array {
 *     return str_starts_with($value, 'A') ? [] : ['must_start_with_A'];
 * });
 *
 * $rule->validate('Alice'); // []
 * $rule->validate('Bob');   // ['must_start_with_A']
 * ```
 */
final class CallbackRule extends AbstractRule
{
    /**
     * @param \Closure(string):list<string> $validator
     * A callable that returns an array of error codes.
     */
    public function __construct(
        private readonly \Closure $validator
    ) {
    }
    
    /**
     * @inheritDoc
     */
    #[\Override]
    public function validate(string $value, FieldDefinition $field): array
    {
        /** @var list<string> $errors */
        $errors = ($this->validator)($value);
        
        // Ensure type safety — return an array of strings only
        return array_values(array_filter($errors, static fn(string $e): bool => $e !== ''));
    }
}
