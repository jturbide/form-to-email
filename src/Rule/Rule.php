<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;

/**
 * Interface: Rule
 *
 * Defines the contract that every validation rule must follow.
 * Validation rules are small, focused objects that evaluate
 * a given string value and return a list of error identifiers.
 *
 * A field can have multiple rules — they are executed in order,
 * and all errors are collected (non-short-circuiting).
 *
 * The returned errors are meant to be language-agnostic codes,
 * such as `required`, `invalid_email`, or `too_short`,
 * which the frontend can map to localized messages.
 *
 * Example:
 * ```php
 * $rule = new RegexRule('/^[A-Za-z]+$/', 'invalid_letters');
 * $errors = $rule->validate('123'); // ['invalid_letters']
 * ```
 */
interface Rule extends FieldProcessor
{
    /**
     * Validate a given value.
     *
     * @param mixed $value The raw input value.
     * @param FieldDefinition $field The field definition being validated.
     *
     * @return list<string> A list of error identifiers.
     *                      Empty list means the rule passed successfully.
     */
    public function validate(mixed $value, FieldDefinition $field): array;
}
