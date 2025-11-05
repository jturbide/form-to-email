<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\ErrorFactory;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationError;

/**
 * Base class for all validation rules.
 *
 * Provides a consistent high-level template:
 *  - Calls {@see AbstractRule::validate()} for subclasses to perform checks.
 *  - Automatically normalizes all returned results through {@see ErrorFactory}.
 *  - Adds them to the active {@see FormContext}.
 *
 * Example:
 * ```php
 * final class RequiredRule extends AbstractRule
 * {
 *     protected function validate(mixed $value, FieldDefinition $field): array
 *     {
 *         if ($value === null || $value === '') {
 *             return ['required'];
 *         }
 *         return [];
 *     }
 * }
 * ```
 */
abstract readonly class AbstractRule implements FieldProcessor
{
    /**
     * Perform validation and return raw error representations.
     *
     * Implementations may return either:
     *  - A list of string codes (`['required']`), or
     *  - A list of {@see ErrorDefinition} objects for full control.
     *
     * @return list<string|ErrorDefinition>
     */
    protected function validate(mixed $value, FieldDefinition $field): array
    {
        return [];
    }
    
    #[\Override]
    public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
    {
        $results = $this->validate($value, $field);
        
        foreach ($results as $raw) {
            // Normalize any string|array|ValidationError into a ValidationError
            $error = ErrorFactory::normalize($raw, $field);
            $context->addError($field->getName(), $error);
        }
        
        return $value;
    }
}
