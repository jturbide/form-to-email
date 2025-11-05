<?php

declare(strict_types=1);

namespace FormToEmail\Core;

use FormToEmail\Enum\FieldRole;
use FormToEmail\Filter\Filter;
use FormToEmail\Rule\Rule;

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
 *     sanitizer: [new LowercaseFilter(), new TrimFilter()]
 * );
 * ```
 */
final class FieldDefinition
{
    /**
     * @param string $name
     *   Field identifier (e.g., "email" or "message").
     *
     * @param list<FieldRole> $roles
     *   Semantic roles describing how this field maps to email metadata.
     *
     * @param list<FieldProcessor> $processors
     *   Rules and filters to apply to the field
     */
    public function __construct(
        public string $name,
        public array $roles = [],
        public array $processors = [],
    ) {
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getRoles(): array
    {
        return $this->roles;
    }
    
    /**
     * @return FieldProcessor[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
    
    public function addProcessor(FieldProcessor $processor): self
    {
        $this->processors[] = $processor;
        return $this;
    }
    
    /**
     * Convenience alias for adding a Filter
     */
    public function addFilter(Filter $filter): self
    {
        return $this->addProcessor($filter);
    }
    
    /**
     * Convenience alias for adding a Rule
     */
    public function addRule(Rule $rule): self
    {
        return $this->addProcessor($rule);
    }
}
