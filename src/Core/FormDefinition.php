<?php

declare(strict_types=1);

namespace FormToEmail\Core;

/**
 * Represents a complete form schema composed of multiple
 * {@see FieldDefinition} objects.
 *
 * This version is context-driven: all validation and sanitization
 * happens through a shared {@see FormContext}, giving processors
 * access to the full form state.
 *
 * Responsibilities:
 *  - Hold all field definitions
 *  - Validate input through their processors
 *  - Collect normalized data and structured errors
 *  - Return an immutable {@see ValidationResult}
 */
final class FormDefinition
{
    /** @var array<string, FieldDefinition> */
    private array $fields = [];
    
    /**
     * Adds a field to the form definition.
     *
     * @return $this
     */
    public function add(FieldDefinition $field): self
    {
        $this->fields[$field->getName()] = $field;
        return $this;
    }
    
    /**
     * @return array<string, FieldDefinition>
     */
    public function fields(): array
    {
        return $this->fields;
    }
    
    /**
     * Process an input payload against all field definitions.
     *
     * Processors:
     * - Sanitize: Filters
     * - Validate: Rules
     * - Format: Transformers
     *
     * @param array<string, mixed> $input Raw user input (e.g. $_POST / JSON)
     */
    public function process(array $input): ValidationResult
    {
        // Shared state for the entire validation run
        $context = new FormContext($input);
        
        // Run every field’s processors sequentially
        foreach ($this->fields as $field) {
            $name = $field->getName();
            $value = $input[$name] ?? null;
            
            foreach ($field->getProcessors() as $processor) {
                $value = $processor->process($value, $field, $context);
            }
            
            // Always store the normalized value back into context
            $context->setValue($name, $value);
        }
        
        return new ValidationResult(
            valid: !$context->hasAnyErrors(),
            errors: $context->allErrors(),
            data: $context->allData(),
        );
    }
}
