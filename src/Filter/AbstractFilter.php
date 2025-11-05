<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * Class AbstractFilter
 *
 * Provides a base implementation for filters.
 * Useful for creating lightweight filters without
 * re-implementing boilerplate every time.
 *
 * Filters extending this class should implement
 * the {@see AbstractTransformer::apply()} method.
 */
abstract class AbstractFilter implements Filter
{
    /**
     * Whether this filter supports the given field.
     *
     * By default, all fields are supported. Override this
     * in child classes to target specific FieldRoles
     * or field types.
     *
     * @param FieldDefinition $field
     * @return bool
     */
    public function supports(FieldDefinition $field): bool
    {
        return true;
    }
    
    /**
     * Apply the filter to the given value.
     *
     * Must be implemented by subclasses.
     *
     * @param mixed $value The raw or intermediate field value.
     * @param FieldDefinition $field The field definition being filtered.
     *
     * @return mixed The sanitized or transformed value.
     */
    abstract public function apply(mixed $value, FieldDefinition $field): mixed;
    
    /**
     * Convenience method to apply filter conditionally
     * only if {@see supports()} returns true.
     *
     * @param mixed $value
     * @param FieldDefinition $field
     *
     * @return mixed
     */
    public function filter(mixed $value, FieldDefinition $field): mixed
    {
        return $this->supports($field)
            ? $this->apply($value, $field)
            : $value;
    }
    
    public function process(mixed $value, FieldDefinition $field, array &$errors): mixed
    {
        return $this->filter($value, $field);
    }
}
