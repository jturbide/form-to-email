<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use Closure;
use FormToEmail\Core\FieldDefinition;

/**
 * Class CallbackFilter
 *
 * Wraps an arbitrary callback for custom filtering logic.
 * Useful for quick inline sanitization or project-specific tweaks.
 *
 * Example:
 *   new CallbackFilter(fn($v) => str_replace('-', '', $v))
 */
class CallbackFilter extends AbstractFilter
{
    /**
     * @var Closure
     */
    protected Closure $callback;
    
    /**
     * @param callable(mixed, FieldDefinition): mixed $callback
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
