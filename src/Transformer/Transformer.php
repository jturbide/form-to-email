<?php

declare(strict_types=1);

namespace FormToEmail\Transformer;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;
use FormToEmail\Core\FormContext;

/**
 * Interface Transformer
 *
 * Defines a contract for transforming
 * field input values before or after validation.
 *
 * Transformer should be idempotent (safe to reapply)
 * and stateless where possible.
 */
interface Transformer extends FieldProcessor
{
    /**
     * Apply the transformer to the given value.
     *
     * @param mixed $value The raw or intermediate field value.
     * @param FieldDefinition $field The field definition being transformed.
     * @param FormContext $context Shared context for cross-field access.
     *
     * @return mixed The transformed value.
     */
    public function apply(mixed $value, FieldDefinition $field, FormContext $context): mixed;
}
