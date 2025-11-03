<?php

declare(strict_types = 1);

namespace FormToEmail\Core;

/**
 * Class: FormDefinition
 *
 * Represents a complete form schema composed of multiple
 * {@see FieldDefinition} objects.
 *
 * Responsibilities:
 * - Store the schema of all defined fields.
 * - Validate incoming input arrays.
 * - Collect sanitized data and error codes.
 * - Return an immutable {@see ValidationResult}.
 *
 * Example:
 * ```php
 * $form = (new FormDefinition())
 *     ->add(new FieldDefinition('name', roles: [FieldRole::SenderName], rules: [...]))
 *     ->add(new FieldDefinition('email', roles: [FieldRole::SenderEmail], rules: [...]));
 *
 * $result = $form->validate($_POST);
 *
 * if ($result->valid) { ... }
 * ```
 */
final class FormDefinition
{
    /**
     * @var array<string, FieldDefinition>
     * Internal registry of fields, indexed by field name.
     */
    private array $fields = [];
    
    /**
     * Adds a field to the form definition.
     *
     * @return $this
     */
    public function add(FieldDefinition $field): self
    {
        $this->fields[$field->name] = $field;
        return $this;
    }
    
    /**
     * Returns all field definitions as an associative array.
     *
     * @return array<string, FieldDefinition>
     */
    public function fields(): array
    {
        return $this->fields;
    }
    
    /**
     * Validates the given input array against all field definitions.
     *
     * Each field’s rules are evaluated in order.
     * All errors are collected per field (no short-circuiting),
     * and sanitizers are applied to values that pass validation.
     *
     * @param array<string, mixed> $input Raw input (e.g. decoded JSON or $_POST)
     */
    public function validate(array $input): ValidationResult
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];
        
        /** @var array<string, string> $data */
        $data = [];
        
        foreach ($this->fields as $field) {
            // Retrieve raw value and normalize it to a trimmed string
            $raw = (string)($input[$field->name] ?? '');
            $value = trim($raw);
            
            // Collect errors from all rules
            $fieldErrors = [];
            foreach ($field->rules as $rule) {
                $fieldErrors = [...$fieldErrors, ...$rule->validate($value)];
            }
            
            // If there are validation errors, record them
            if (!empty($fieldErrors)) {
                $errors[$field->name] = $fieldErrors;
            }
            
            // Always store sanitized or raw value
            $data[$field->name] = $field->sanitizer
                ? ($field->sanitizer)($value)
                : $value;
        }
        
        // Return the immutable result object
        return new ValidationResult(valid: empty($errors), errors: $errors, data: $data);
    }
}
