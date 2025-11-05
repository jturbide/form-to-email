<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;

/**
 * Abstract base for length-based rules.
 *
 * Provides common logic for measuring string length,
 * handling multibyte awareness, and generating structured
 * {@see ErrorDefinition} instances.
 */
abstract readonly class AbstractLengthRule extends AbstractRule
{
    /**
     * @param bool   $multibyte  Whether to use `mb_strlen()` for UTF-8 safety.
     * @param string $encoding   Encoding for `mb_strlen()` (defaults to 'UTF-8').
     */
    public function __construct(
        protected readonly bool $multibyte = true,
        protected readonly string $encoding = 'UTF-8',
    ) {
    }
    
    /**
     * Get the length of the given value.
     *
     * @param string $value
     */
    protected function getLength(string $value): int
    {
        return $this->multibyte
            ? mb_strlen($value, $this->encoding)
            : strlen($value);
    }
    
    /**
     * Create a structured error definition for a field.
     *
     * @param array<non-empty-string, mixed> $context  Extra key-value pairs (e.g. min/max/actual)
     */
    protected function makeError(
        FieldDefinition $field,
        string $code,
        string $message,
        array $context = []
    ): ErrorDefinition {
        return new ErrorDefinition(
            code: $code,
            message: $message,
            context: ['field' => $field->getName(), ...$context],
            field: $field->getName(),
        );
    }
}
