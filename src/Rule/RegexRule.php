<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;

/**
 * Rule: RegexRule
 *
 * Validates that an input value matches a given regular expression.
 * The rule is intentionally minimal — it does **not** alter the pattern,
 * inject flags, or modify developer intent.
 *
 * Combine with RequiredRule for mandatory fields.
 */
final readonly class RegexRule extends AbstractRule
{
    /** @var non-empty-string */
    private string $pattern;
    
    /** @var non-empty-string */
    private string $error;
    
    /**
     * @param non-empty-string $pattern Regular expression with delimiters (e.g. `/^[A-Z]+$/u`)
     * @param non-empty-string $error   Error code when validation fails.
     */
    public function __construct(string $pattern, string $error = 'invalid_format')
    {
        $this->assertValidPattern($pattern);
        
        $this->pattern = $pattern;
        $this->error = $error;
    }
    
    /**
     * Validate that the provided regex compiles successfully.
     * @param non-empty-string $pattern
     */
    private function assertValidPattern(string $pattern): void
    {
        set_error_handler(static function (): void {
            throw new \InvalidArgumentException('Invalid regex pattern provided.');
        });
        
        try {
            if (@preg_match($pattern, '') === false) {
                throw new \InvalidArgumentException("Invalid regex pattern: {$pattern}");
            }
        } finally {
            restore_error_handler();
        }
    }
    
    #[\Override]
    public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
    {
        // Ignore empty or non-string values (handled by RequiredRule)
        if (!is_string($value) || $value === '') {
            return $value;
        }
        
        /** @var non-empty-string $pattern */
        $pattern = $this->pattern;
        
        $result = @preg_match($pattern, $value);
        
        if ($result !== 1) {
            $context->addError(
                $field->getName(),
                new ErrorDefinition(
                    code: $this->error,
                    message: sprintf('Value does not match pattern: %s', $pattern),
                    context: ['value' => $value, 'pattern' => $pattern],
                    field: $field->getName()
                )
            );
        }
        
        return $value;
    }
}
