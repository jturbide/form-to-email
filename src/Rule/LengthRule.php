<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\ErrorDefinition;

/**
 * Rule: LengthRule
 *
 * Ensures a string’s length is between a given minimum and/or maximum.
 *
 * Empty strings are considered valid. Combine with {@see RequiredRule}
 * for mandatory fields.
 *
 * Supports both ASCII and multibyte measurement via `mb_strlen()`.
 */
final readonly class LengthRule extends AbstractLengthRule
{
    public function __construct(
        private ?int $min = null,
        private ?int $max = null,
        private string $tooShortCode = 'too_short',
        private string $tooLongCode = 'too_long',
        private string $tooShortMessage = 'The field "{field}" must be at least {min} characters long.',
        private string $tooLongMessage = 'The field "{field}" must not exceed {max} characters.',
        bool $multibyte = true,
        string $encoding = 'UTF-8',
    ) {
        parent::__construct($multibyte, $encoding);
    }
    
    #[\Override]
    protected function validate(mixed $value, FieldDefinition $field): array
    {
        // Non-string values are ignored (other rules handle type enforcement)
        if (!is_string($value) || $value === '') {
            return [];
        }
        
        $len = $this->getLength($value);
        $errors = [];
        
        if ($this->min !== null && $len < $this->min) {
            $errors[] = $this->makeError($field, $this->tooShortCode, $this->tooShortMessage, [
                'min' => $this->min,
                'length' => $len,
            ]);
        }
        
        if ($this->max !== null && $len > $this->max) {
            $errors[] = $this->makeError($field, $this->tooLongCode, $this->tooLongMessage, [
                'max' => $this->max,
                'length' => $len,
            ]);
        }
        
        return $errors;
    }
}
