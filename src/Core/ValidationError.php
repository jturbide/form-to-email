<?php

declare(strict_types=1);

namespace FormToEmail\Core;

/**
 * Represents a normalized validation issue (field-level or form-level).
 *
 * This interface defines the immutable contract for validation errors
 * returned or added to the {@see FormContext}.
 *
 * Implementations should:
 *  - Be immutable and serializable (for API or logging use)
 *  - Contain machine-readable codes (e.g. "required", "too_short")
 *  - Optionally include a human-readable message and structured context
 *
 * Example concrete class: {@see ErrorDefinition}.
 */
interface ValidationError
{
    /**
     * A machine-friendly error identifier.
     *
     * Example:
     *  - "required"
     *  - "too_short"
     *  - "invalid_email"
     */
    public function getCode(): string;
    
    /**
     * A human-friendly description of the issue.
     *
     * Should be free of markup — the rendering layer (UI, API, template)
     * is responsible for formatting and localization.
     *
     * Example: "This field is required."
     */
    public function getMessage(): string;
    
    /**
     * Arbitrary structured data related to this error, useful for
     * localization placeholders, templating, or debugging.
     *
     * Example:
     *  [
     *      'min' => 3,
     *      'max' => 10,
     *      'actual' => 2,
     *  ]
     *
     * @return array<non-empty-string, mixed>
     */
    public function getContext(): array;
    
    /**
     * The field name this error applies to, or null for a global/form-level error.
     *
     * This allows your system to represent both field-specific errors
     * and high-level ones like "captcha_failed" or "submission_blocked".
     */
    public function getField(): ?string;
}
