<?php

declare(strict_types=1);

namespace FormToEmail\Transformer;

use Closure;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;

/**
 * Transformer: CallbackTransformer
 *
 * Flexible wrapper around a user-provided callable for ad-hoc transformations.
 *
 * Supports 1–3 parameters automatically:
 *  • (mixed $value)
 *  • (mixed $value, FieldDefinition $field)
 *  • (mixed $value, FieldDefinition $field, FormContext $context)
 *
 * Example:
 * ```php
 * new CallbackTransformer(fn($v) => trim((string)$v));
 * new CallbackTransformer(fn($v, $f) => strtoupper($v) . '-' . $f->getName());
 * new CallbackTransformer(fn($v, $f, $c) => $c->get($f->getName()) ?? $v);
 * ```
 */
final class CallbackTransformer extends AbstractTransformer
{
    /** @var Closure(mixed, FieldDefinition=, FormContext=): mixed */
    protected Closure $callback;
    
    /** Cached callable arity (1-3) for fast reuse */
    private int $arity;
    
    /**
     * @param callable(mixed, FieldDefinition=, FormContext=): mixed|Closure(mixed, FieldDefinition=, FormContext=): mixed $callback
     */
    public function __construct(callable|Closure $callback)
    {
        // Normalize to a Closure
        $this->callback = $callback instanceof Closure ? $callback : $callback(...);
        
        // Determine callable arity once
        $ref = new \ReflectionFunction(\Closure::fromCallable($this->callback));
        $this->arity = $ref->getNumberOfParameters();
    }
    
    /**
     * Applies the callback according to its declared parameter count.
     */
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field, FormContext $context): mixed
    {
        return match ($this->arity) {
            3 => ($this->callback)($value, $field, $context),
            2 => ($this->callback)($value, $field),
            1 => ($this->callback)($value),
            default => throw new \ArgumentCountError(
                sprintf('CallbackTransformer: unsupported parameter count %d', $this->arity)
            ),
        };
    }
}
