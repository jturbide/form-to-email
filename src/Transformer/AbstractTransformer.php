<?php

declare(strict_types=1);

namespace FormToEmail\Transformer;

use FormToEmail\Core\FieldDefinition;

/**
 * Class AbstractTransform
 *
 * Provides a base implementation for transforms.
 * Useful for creating lightweight transforms without
 * re-implementing boilerplate every time.
 *
 * Transforms extending this class should implement
 * the {@see AbstractTransformer::apply()} method.
 */
abstract class AbstractTransformer implements Transformer
{
    /**
     * Whether this transform supports the given field.
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
     * Apply the transform to the given value.
     *
     * Must be implemented by subclasses.
     *
     * @param mixed $value The raw or intermediate field value.
     * @param FieldDefinition $field The field definition being transformed.
     *
     * @return mixed The sanitized or transformed value.
     */
    abstract public function apply(mixed $value, FieldDefinition $field): mixed;
    
    /**
     * Convenience method to apply transform conditionally
     * only if {@see supports()} returns true.
     *
     * @param mixed $value
     * @param FieldDefinition $field
     *
     * @return mixed
     */
    public function transform(mixed $value, FieldDefinition $field): mixed
    {
        return $this->supports($field)
            ? $this->apply($value, $field)
            : $value;
    }
    
    public function process(mixed $value, FieldDefinition $field, array &$errors): mixed
    {
        return $this->transform($value, $field);
    }
}
