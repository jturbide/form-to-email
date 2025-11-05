<?php

declare(strict_types=1);

namespace FormToEmail\Core;

/**
 * Immutable, structured representation of a validation error.
 *
 * Each instance carries:
 *  - A stable code ("required", "too_short", "invalid_email")
 *  - A human-readable message (for logs / default rendering)
 *  - A typed context array of variables usable for i18n templating
 *  - An optional field name the error pertains to
 *
 * @api
 */
final readonly class ErrorDefinition implements ValidationError
{
    /**
     * @param string                        $code     Stable machine-readable code
     * @param string                        $message  Optional human-friendly text
     * @param array<non-empty-string, mixed> $context  Extra info (min, max, actual, …)
     * @param string|null                   $field    Related field or null for global
     */
    public function __construct(
        private string $code,
        private string $message = '',
        private array $context = [],
        private ?string $field = null,
    ) {
    }
    
    // ---------------------------------------------------------------------
    // Interface Implementation
    // ---------------------------------------------------------------------
    
    #[\Override]
    public function getCode(): string
    {
        return $this->code;
    }
    
    #[\Override]
    public function getMessage(): string
    {
        return $this->message;
    }
    
    /**
     * @return array<non-empty-string, mixed>
     */
    #[\Override]
    public function getContext(): array
    {
        return $this->context;
    }
    
    #[\Override]
    public function getField(): ?string
    {
        return $this->field;
    }
    
    // ---------------------------------------------------------------------
    // Helper Methods
    // ---------------------------------------------------------------------
    
    /**
     * Returns a message string with {placeholders} replaced by context values.
     * E.g. "Min {min}, got {actual}" → "Min 3, got 1".
     */
    public function interpolate(): string
    {
        $result = $this->message;
        foreach ($this->context as $key => $value) {
            $result = str_replace('{' . $key . '}', (string)$value, $result);
        }
        return $result;
    }
    
    /**
     * Convert to associative array for JSON serialization.
     *
     * @return array{
     *     code: string,
     *     message: string,
     *     context: array<non-empty-string, mixed>,
     *     field: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
            'field' => $this->field,
        ];
    }
    
    /** String representation — usually the interpolated message. */
    public function __toString(): string
    {
        return $this->interpolate();
    }
    
    /**
     * Create a new instance with a different message (for translators).
     */
    public function withMessage(string $message): self
    {
        return new self($this->code, $message, $this->context, $this->field);
    }
    
    /**
     * Create a new instance with merged context values.
     *
     * @param array<non-empty-string, mixed> $extra
     */
    public function withContext(array $extra): self
    {
        return new self($this->code, $this->message, [...$this->context, ...$extra], $this->field);
    }
    
    /**
     * Create a new instance bound to a specific field.
     */
    public function forField(string $field): self
    {
        return new self($this->code, $this->message, $this->context, $field);
    }
}
