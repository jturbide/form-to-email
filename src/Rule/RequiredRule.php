<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;

/**
 * Rule: RequiredRule
 *
 * Ensures that a value is present and non-empty.
 *
 * This rule treats as *empty*:
 *  - `null`
 *  - empty string (`""`) after optional trimming
 *  - empty array (`[]`)
 *  - `false`
 *
 * Everything else (including `0`, `"0"`, or non-empty arrays) is valid.
 *
 * # Example
 *
 * ```php
 * $rule = new RequiredRule();
 * $rule->validate('', $field);   // → [ErrorDefinition('required')]
 * $rule->validate('John', $field); // → []
 * ```
 */
final readonly class RequiredRule extends AbstractRule
{
    /**
     * @param string $errorCode  Stable identifier (default: `'required'`)
     * @param string $message    Default message (can include `{field}` placeholder)
     * @param bool   $trim       Whether to trim strings before checking emptiness
     */
    public function __construct(
        private string $errorCode = 'required',
        private string $message = 'The field "{field}" is required.',
        private bool $trim = true,
    ) {
    }
    
    /**
     * @inheritDoc
     *
     * @return list<ErrorDefinition>
     */
    #[\Override]
    protected function validate(mixed $value, FieldDefinition $field): array
    {
        // Null → empty
        if ($value === null) {
            return [$this->makeError($field)];
        }
        
        // Boolean false → empty
        if ($value === false) {
            return [$this->makeError($field)];
        }
        
        // Empty array → empty
        if (is_array($value) && count($value) === 0) {
            return [$this->makeError($field)];
        }
        
        // String check (optionally trimmed)
        if (is_string($value)) {
            $checked = $this->trim ? trim($value) : $value;
            if ($checked === '') {
                return [$this->makeError($field)];
            }
        }
        
        // Objects or non-empty values pass
        return [];
    }
    
    /**
     * Create a structured ErrorDefinition for this rule.
     */
    private function makeError(FieldDefinition $field): ErrorDefinition
    {
        return new ErrorDefinition(
            code: $this->errorCode,
            message: $this->message,
            context: ['field' => $field->getName()],
            field: $field->getName()
        );
    }
}
