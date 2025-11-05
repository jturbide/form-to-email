<?php

declare(strict_types=1);

namespace FormToEmail\Transformer;

use Closure;
use FormToEmail\Core\FieldDefinition;

/**
 * Class CallbackTransformer
 *
 * Wraps an arbitrary callback for custom transformer logic.
 * Useful for quick inline sanitization or project-specific tweaks.
 *
 * Example:
 *   new CallbackTransformer(fn($v) => str_replace('-', '', $v))
 */
final class CallbackTransformer extends AbstractTransformer
{
    /**
     * @var Closure
     */
    protected Closure $callback;
    
    /**
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        // Convert any callable into a Closure (first-class callable syntax)
        $this->callback = $callback instanceof Closure ? $callback : $callback(...);
    }
    
    /**
     * @inheritDoc
     */
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        return ($this->callback)($value, $field);
    }
}
