<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;

/**
 * Interface Filter
 *
 * Defines a contract for transforming or sanitizing
 * field input values before or after validation.
 *
 * Filters should be idempotent (safe to reapply)
 * and stateless where possible.
 */
interface Filter extends FieldProcessor
{
    /**
     * Apply the filter to the given value.
     *
     * @param mixed $value The raw or intermediate field value.
     * @param FieldDefinition $field The field definition being filtered.
     *
     * @return mixed The sanitized or transformed value.
     */
    public function apply(mixed $value, FieldDefinition $field): mixed;
}
