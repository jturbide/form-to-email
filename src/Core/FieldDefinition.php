<?php

declare(strict_types=1);

namespace FormToEmail\Core;

use FormToEmail\Enum\FieldRole;
use FormToEmail\Validation\Rule;

/**
 * Class: FieldDefinition
 *
 * Represents a single form field within a form definition.
 * Each field defines:
 * - its unique name (e.g., "email", "message"),
 * - semantic roles (for auto-mapping to email headers),
 * - a list of validation rules,
 * - and an optional sanitizer function.
 *
 * Fields are designed to be immutable — once created, their
 * configuration does not change at runtime. This improves
 * predictability and allows safe reuse across forms or projects.
 *
 * Example:
 * ```php
 * $field = new FieldDefinition(
 *     name: 'email',
 *     roles: [FieldRole::SenderEmail],
 *     rules: [new RequiredRule(), new EmailRule()],
 *     sanitizer: static fn(string $v) => filter_var($v, FILTER_SANITIZE_EMAIL)
 * );
 * ```
 */
final readonly class FieldDefinition
{
    /**
     * @param string $name
     *   Field identifier (e.g., "email" or "message").
     *
     * @param list<FieldRole> $roles
     *   Semantic roles describing how this field maps to email metadata.
     *
     * @param list<Rule> $rules
     *   Validation rules applied to the field.
     *
     * @param ?\Closure(string):string $sanitizer
     *   Optional sanitizer applied after validation.
     */
    public function __construct(
        public string $name,
        public array $roles = [],
        public array $rules = [],
        public ?\Closure $sanitizer = null,
    ) {
    }
}
