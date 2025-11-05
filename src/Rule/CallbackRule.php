<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use Closure;
use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;

/**
 * Rule: CallbackRule
 *
 * Provides a highly flexible, developer-defined validation mechanism.
 * Accepts any callable returning string codes or {@see ErrorDefinition} instances.
 *
 * The callback signature supports:
 *  - (mixed $value)
 *  - (mixed $value, FieldDefinition $field)
 *  - (mixed $value, FieldDefinition $field, FormContext $context)
 *
 * Example:
 * ```php
 * $rule = new CallbackRule(
 *     static fn(string $v): array => str_starts_with($v, 'A') ? [] : ['must_start_with_A']
 * );
 * ```
 */
final readonly class CallbackRule extends AbstractRule
{
    public function __construct(
        private Closure $validator,
    ) {
    }
    
    /**
     * Fallback for compatibility with validation-only calls (no context).
     *
     * @return list<ErrorDefinition>
     */
    #[\Override]
    protected function validate(mixed $value, FieldDefinition $field): array
    {
        $ctx = new FormContext([$field->getName() => $value]);
        
        /** @var mixed $result */
        $result = ($this->validator)($value, $field, $ctx);
        
        if (!is_array($result)) {
            throw new \UnexpectedValueException(
                sprintf('CallbackRule validator must return an array, got %s', get_debug_type($result))
            );
        }
        
        /** @var list<ErrorDefinition> $errors */
        $errors = [];
        
        foreach ($result as $err) {
            if (is_string($err)) {
                $errors[] = new ErrorDefinition(
                    code: $err,
                    message: ucfirst(str_replace('_', ' ', $err)),
                    context: ['field' => $field->getName()],
                    field: $field->getName()
                );
            } elseif ($err instanceof ErrorDefinition) {
                $errors[] = $err;
            } else {
                throw new \UnexpectedValueException(
                    sprintf('Invalid error type returned by CallbackRule: %s', get_debug_type($err))
                );
            }
        }
        
        return $errors;
    }
    
    /**
     * Full context-aware process version.
     */
    #[\Override]
    public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
    {
        /** @var mixed $result */
        $result = ($this->validator)($value, $field, $context);
        
        if (!is_array($result)) {
            throw new \UnexpectedValueException(
                sprintf('CallbackRule validator must return an array, got %s', get_debug_type($result))
            );
        }
        
        foreach ($result as $err) {
            if (is_string($err)) {
                $context->addError(
                    $field->getName(),
                    new ErrorDefinition(
                        code: $err,
                        message: ucfirst(str_replace('_', ' ', $err)),
                        context: ['field' => $field->getName()],
                        field: $field->getName()
                    )
                );
            } elseif ($err instanceof ErrorDefinition) {
                $context->addError($field->getName(), $err);
            } else {
                throw new \UnexpectedValueException(
                    sprintf('Invalid error type returned by CallbackRule: %s', get_debug_type($err))
                );
            }
        }
        
        return $value;
    }
}
