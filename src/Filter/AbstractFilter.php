<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;
use FormToEmail\Core\FormContext;

/**
 * Base class for all filters (sanitizers / transformers).
 *
 * Filters are processors that *always* return a value
 * (never add errors). They can modify or normalize the
 * input value for their associated field.
 *
 * Subclasses typically implement {@see AbstractFilter::apply()}.
 *
 * # Example
 *
 * ```php
 * final class TrimFilter extends AbstractFilter
 * {
 *     protected function apply(mixed $value, FieldDefinition $field): mixed
 *     {
 *         return is_string($value) ? trim($value) : $value;
 *     }
 * }
 * ```
 *
 * They can also override {@see supports()} to selectively
 * target specific field types or roles.
 */
abstract class AbstractFilter implements FieldProcessor
{
    // ---------------------------------------------------------------------
    // Field targeting
    // ---------------------------------------------------------------------
    
    /**
     * Whether this filter should be applied to a given field.
     *
     * Override in subclasses to limit scope by role or name.
     *
     * @return bool True if this filter applies to the field.
     */
    public function supports(FieldDefinition $field): bool
    {
        return true;
    }
    
    // ---------------------------------------------------------------------
    // Core behavior
    // ---------------------------------------------------------------------
    
    /**
     * Apply the transformation logic.
     *
     * Must be implemented by subclasses.
     *
     * @param mixed $value Current field value.
     * @param FieldDefinition $field Associated field definition.
     *
     * @return mixed The transformed / normalized value.
     */
    abstract protected function apply(mixed $value, FieldDefinition $field): mixed;
    
    /**
     * Apply the filter if supported, otherwise return the value unchanged.
     *
     * This method is used internally by {@see process()} but can also be
     * called directly for ad-hoc usage in tests or utility scripts.
     */
    protected function filter(mixed $value, FieldDefinition $field): mixed
    {
        return $this->supports($field)
            ? $this->apply($value, $field)
            : $value;
    }
    
    // ---------------------------------------------------------------------
    // Unified processing pipeline
    // ---------------------------------------------------------------------
    
    /**
     * Execute the filter within the unified processor interface.
     *
     * Filters are pure transformations — they do not add validation errors.
     * However, they can still use {@see FormContext} to read or modify
     * other fields if necessary (e.g. normalization dependencies).
     */
    #[\Override]
    public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
    {
        $newValue = $this->filter($value, $field);
        
        // Persist the transformed value immediately into context
        $context->setValue($field->getName(), $newValue);
        
        return $newValue;
    }
}
