<?php

declare(strict_types=1);

namespace FormToEmail\Validation\Rules;

use FormToEmail\Validation\Rule;

/**
 * Rule: RequiredRule
 *
 * Ensures that the input value is not empty.
 *
 * This rule simply checks whether a trimmed string is empty.
 * It's often the first rule applied to mandatory fields such as
 * name, email, or message.
 *
 * Since your project avoids direct human-readable messages on
 * the backend, this rule returns a single standardized code:
 * `"required"`.
 *
 * Example:
 * ```php
 * $rule = new RequiredRule();
 * $rule->validate('');        // ['required']
 * $rule->validate('John');    // []
 * ```
 */
final class RequiredRule implements Rule
{
    public function __construct(
        /**
         * The error code returned when validation fails.
         * Defaults to `'required'`, but can be overridden
         * if you want more contextual codes.
         */
        private readonly string $error = 'required'
    ) {
    }
    
    /**
     * @inheritDoc
     */
    #[\Override]
    public function validate(string $value): array
    {
        return trim($value) === '' ? [$this->error] : [];
    }
}
