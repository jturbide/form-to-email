<?php

declare(strict_types=1);

namespace FormToEmail\Core;

/**
 * Interface FieldProcessor
 *
 * Represents a single processing step for a field —
 * could be a Filter, a Validator (Rule), or any other
 * transformation that modifies or validates the field value.
 */
interface FieldProcessor
{
    /**
     * Processes a value and optionally appends errors.
     *
     * @param mixed $value The current field value.
     * @param FieldDefinition $field The field definition.
     * @param array<string, string[]> $errors Reference array to append validation errors.
     *
     * @return mixed The (potentially transformed) value.
     */
    public function process(mixed $value, FieldDefinition $field, array &$errors): mixed;
}
