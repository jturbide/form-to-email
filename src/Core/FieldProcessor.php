<?php

declare(strict_types=1);

namespace FormToEmail\Core;

/**
 * Represents a single processing step (filter or validation rule)
 * within the form pipeline.
 *
 * Each processor receives:
 *  - The current field value
 *  - Its field definition (metadata, roles, etc.)
 *  - The current form context (to read/write other values or add errors)
 *
 * Processors may:
 *  - Transform and return a new value
 *  - Add one or more {@see ValidationError} instances to the context
 *
 * This unified interface allows both filters and validators to coexist
 * in a single sequential pipeline for each field.
 */
interface FieldProcessor
{
    /**
     * Execute the processor.
     *
     * @param mixed $value
     *        Current field value before this processor runs.
     *
     * @param FieldDefinition $field
     *        Metadata and configuration for this field.
     *
     * @param FormContext $context
     *        Shared state object — contains all input, data, and errors.
     *
     * @return mixed The transformed value after processing.
     */
    public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed;
}
