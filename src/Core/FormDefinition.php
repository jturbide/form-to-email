<?php

declare(strict_types=1);

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
 *     ->add(new FieldDefinition('name', roles: [FieldRole::SenderName], processors: [...]))
 *     ->add(new FieldDefinition('email', roles: [FieldRole::SenderEmail], processors: [...]));
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
            $name = $field->getName();
            $value = $input[$name] ?? null;
            $fieldErrors = [];
            
            foreach ($field->getProcessors() as $processor) {
                $value = $processor->process($value, $field, $fieldErrors);
            }
            
            $data[$name] = $value;
            
            if (!empty($fieldErrors)) {
                $errors[$name] = $fieldErrors;
            }
        }
        
        // Return the immutable result object
        return new ValidationResult(valid: empty($errors), errors: $errors, data: $data);
    }
}
