<?php

declare(strict_types=1);

namespace FormToEmail\Core;

use JsonSerializable;

/**
 * Immutable result object representing the outcome of form validation.
 *
 * Carries structured {@see ValidationError} objects instead of plain strings,
 * enabling:
 *  - i18n-friendly message codes + variables
 *  - richer diagnostics (min/max/actual, offending value, etc.)
 *  - predictable API serialization (stable keys, typed values)
 *
 * @psalm-type ErrorMap = array<string, list<ValidationError>>
 * @phpstan-type ErrorMap array<string, list<ValidationError>>
 */
final readonly class ValidationResult implements JsonSerializable
{
    /**
     * @param bool                                 $valid   Overall success flag
     * @param array<string, list<ValidationError>> $errors  Field → list of errors
     * @param array<string, mixed>                 $data    Normalized values
     */
    public function __construct(
        public bool $valid,
        public array $errors,
        public array $data,
    ) {
    }
    
    /**
     * Create a ValidationResult directly from a FormContext snapshot.
     */
    public static function fromContext(FormContext $ctx): self
    {
        return new self(
            valid: !$ctx->hasAnyErrors(),
            errors: $ctx->allErrors(),
            data: $ctx->allData(),
        );
        // Note: immutable snapshot, no live context reference.
    }
    
    // ---------------------------------------------------------------------
    // Predicates
    // ---------------------------------------------------------------------
    
    /** True when validation failed. */
    public function failed(): bool
    {
        return !$this->valid;
    }
    
    /** Whether a specific field contains any errors. */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && $this->errors[$field] !== [];
    }
    
    // ---------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------
    
    /**
     * @return list<ValidationError> All errors for the given field.
     */
    public function getErrors(string $field): array
    {
        /** @var list<ValidationError> */
        return $this->errors[$field] ?? [];
    }
    
    /**
     * Return the first error for a field, if any.
     */
    public function firstError(string $field): ?ValidationError
    {
        $errs = $this->getErrors($field);
        return $errs[0] ?? null;
    }
    
    /**
     * @return array<string, list<ValidationError>> Full error map.
     */
    public function allErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * @return array<string, mixed> Normalized data map.
     */
    public function allData(): array
    {
        return $this->data;
    }
    
    // ---------------------------------------------------------------------
    // Messages & Serialization
    // ---------------------------------------------------------------------
    
    /**
     * Interpolate a single error’s message using its context variables.
     */
    public function interpolate(ValidationError $error): string
    {
        $message = $error->getMessage();
        if ($message === '') {
            return $error->getCode(); // reasonable fallback
        }
        
        $result = $message;
        foreach ($error->getContext() as $key => $value) {
            $result = str_replace('{' . $key . '}', (string) $value, $result);
        }
        return $result;
    }
    
    /**
     * Interpolated messages for a single field (ordered).
     *
     * @return list<string>
     */
    public function messages(string $field): array
    {
        $messages = [];
        foreach ($this->getErrors($field) as $err) {
            $messages[] = $this->interpolate($err);
        }
        return $messages;
    }
    
    /**
     * Interpolated messages for all fields.
     *
     * @return array<string, list<string>>
     */
    public function messagesAll(): array
    {
        $out = [];
        foreach ($this->errors as $field => $errs) {
            $out[$field] = array_map(
                fn(ValidationError $e) => $this->interpolate($e),
                $errs
            );
        }
        return $out;
    }
    
    /**
     * Convert to a serializable array.
     *
     * When `$interpolated` is `false` (default), errors are structured:
     * [
     *   'code'    => string,
     *   'message' => string,
     *   'context' => array<string,mixed>,
     *   'field'   => string|null
     * ]
     *
     * When `$interpolated` is `true`, errors become plain strings.
     *
     * @param bool $interpolated Whether to return interpolated messages
     * @return array{
     *     valid: bool,
     *     errors: array<string, list<string>|list<array{code:string,message:string,context:array<string,mixed>,field:string|null}>>,
     *     data: array<string, mixed>
     * }
     */
    public function toArray(bool $interpolated = false): array
    {
        $errors = [];
        
        foreach ($this->errors as $field => $list) {
            if ($interpolated) {
                $errors[$field] = array_map(
                    fn(ValidationError $e) => $this->interpolate($e),
                    $list
                );
                continue;
            }
            
            $errors[$field] = array_map(
            /**
             * @return array{code:string,message:string,context:array<string,mixed>,field:string|null}
             */
                fn(ValidationError $e) => [
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage(),
                    'context' => $e->getContext(),
                    'field'   => $e->getField(),
                ],
                $list
            );
        }
        
        return [
            'valid'  => $this->valid,
            'errors' => $errors,
            'data'   => $this->data,
        ];
    }
    
    /**
     * JSON representation (non-interpolated by default to preserve structure).
     * Use `json_encode($result->toArray(true))` if you want plain messages.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray(false);
    }
}
