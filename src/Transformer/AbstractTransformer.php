<?php

declare(strict_types=1);

namespace FormToEmail\Transformer;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;
use FormToEmail\Core\FormContext;

/**
 * Base class for all data transformers.
 *
 * Transformers modify or enrich field values — possibly
 * using data from other fields. They run *after* filters
 * and *before* validation rules in most pipelines.
 *
 * Unlike rules, transformers never add validation errors;
 * their purpose is deterministic mutation or aggregation.
 *
 * # Example
 *
 * ```php
 * final class FullNameTransformer extends AbstractTransformer
 * {
 *     #[\Override]
 *     protected function apply(mixed $value, FieldDefinition $field, FormContext $ctx): mixed
 *     {
 *         // Example: combine "first_name" + "last_name" into "full_name"
 *         $first = (string) $ctx->getValue('first_name');
 *         $last  = (string) $ctx->getValue('last_name');
 *         return trim("$first $last");
 *     }
 * }
 * ```
 */
abstract class AbstractTransformer implements FieldProcessor
{
    // ---------------------------------------------------------------------
    // Targeting
    // ---------------------------------------------------------------------
    
    /**
     * Whether this transformer should be applied to a specific field.
     *
     * Override to restrict usage based on name or role.
     */
    public function supports(FieldDefinition $field): bool
    {
        return true;
    }
    
    // ---------------------------------------------------------------------
    // Core transformation
    // ---------------------------------------------------------------------
    
    /**
     * Apply the transformation logic.
     *
     * Implement this in subclasses to perform any deterministic
     * change of value (e.g., merging, normalization, formatting).
     *
     * @param mixed           $value Current field value.
     * @param FieldDefinition $field Associated field definition.
     * @param FormContext     $context Shared context for cross-field access.
     *
     * @return mixed The transformed or enriched value.
     */
    abstract protected function apply(mixed $value, FieldDefinition $field, FormContext $context): mixed;
    
    // ---------------------------------------------------------------------
    // Unified processing
    // ---------------------------------------------------------------------
    
    /**
     * Executes this transformer inside the unified pipeline.
     *
     * If {@see supports()} returns true, applies the transformation
     * and updates the context’s normalized data immediately.
     *
     * Transformers never add validation errors, but they *can*
     * depend on other context values (cross-field logic).
     */
    #[\Override]
    public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
    {
        if (!$this->supports($field)) {
            return $value;
        }
        
        $newValue = $this->apply($value, $field, $context);
        
        // Update the context for downstream processors
        $context->setValue($field->getName(), $newValue);
        
        return $newValue;
    }
}
