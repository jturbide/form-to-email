<?php

declare(strict_types=1);

namespace FormToEmail\Core;

/**
 * Carries input, evolving normalized data, and structured errors
 * across the validation pipeline.
 *
 * Each validation run creates one FormContext instance, which is
 * passed to all processors (rules, filters, etc.).
 *
 * The context allows:
 *  - Reading raw input or normalized values from any field
 *  - Adding field-specific or global (form-level) errors
 *  - Inspecting accumulated data or errors
 *
 * This object is intentionally mutable but encapsulated; callers
 * interact only through high-level methods to ensure consistency.
 *
 * @internal Mutability is restricted to controlled internal methods.
 */
final class FormContext
{
    public const GLOBAL_FIELD = '_form';
    
    /**
     * @var array<string, mixed> Raw user input (e.g. $_POST or decoded JSON)
     */
    private array $input;
    
    /**
     * @var array<string, mixed> Normalized field values
     */
    private array $data;
    
    /**
     * @var array<string, list<ValidationError>> Structured error map
     */
    private array $errors;
    
    /**
     * @param array<string, mixed>                 $input
     * @param array<string, mixed>                 $data
     * @param array<string, list<ValidationError>> $errors
     */
    public function __construct(
        array $input = [],
        array $data = [],
        array $errors = [],
    ) {
        $this->input = $input;
        $this->data = $data;
        $this->errors = $errors;
    }
    
    // ---------------------------------------------------------------------
    // INPUT / DATA ACCESS
    // ---------------------------------------------------------------------
    
    public function getInput(string $field): mixed
    {
        return $this->input[$field] ?? null;
    }
    
    public function getValue(string $field): mixed
    {
        return $this->data[$field] ?? $this->input[$field] ?? null;
    }
    
    public function setValue(string $field, mixed $value): void
    {
        $this->data[$field] = $value;
    }
    
    /** @return array<string, mixed> */
    public function allInput(): array
    {
        return $this->input;
    }
    
    /** @return array<string, mixed> */
    public function allData(): array
    {
        return $this->data;
    }
    
    // ---------------------------------------------------------------------
    // ERROR HANDLING
    // ---------------------------------------------------------------------
    
    public function addError(string $field, ValidationError $error): void
    {
        $this->errors[$field] ??= [];
        $this->errors[$field][] = $error;
    }
    
    public function addGlobalError(ValidationError $error): void
    {
        $this->addError(self::GLOBAL_FIELD, $error);
    }
    
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && $this->errors[$field] !== [];
    }
    
    public function hasAnyErrors(): bool
    {
        foreach ($this->errors as $errs) {
            if ($errs !== []) {
                return true;
            }
        }
        return false;
    }
    
    /** @return list<ValidationError> */
    public function getFieldErrors(string $field): array
    {
        /** @var list<ValidationError> */
        return $this->errors[$field] ?? [];
    }
    
    /** @return array<string, list<ValidationError>> */
    public function allErrors(): array
    {
        return $this->errors;
    }
    
    // ---------------------------------------------------------------------
    // MANAGEMENT / MUTATION HELPERS
    // ---------------------------------------------------------------------
    
    /**
     * @param array<string, list<ValidationError>> $errors
     */
    public function setAllErrors(array $errors): void
    {
        $this->errors = $errors;
    }
    
    public function clearFieldErrors(string $field): void
    {
        unset($this->errors[$field]);
    }
    
    public function clearAllErrors(): void
    {
        $this->errors = [];
    }
    
    // ---------------------------------------------------------------------
    // IMMUTABLE CLONE HELPERS
    // ---------------------------------------------------------------------
    
    public function withValue(string $field, mixed $value): self
    {
        $clone = clone $this;
        $clone->setValue($field, $value);
        return $clone;
    }
    
    public function withError(string $field, ValidationError $error): self
    {
        $clone = clone $this;
        $clone->addError($field, $error);
        return $clone;
    }
}
