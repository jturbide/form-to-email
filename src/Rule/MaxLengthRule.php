<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\FieldDefinition;

/**
 * Rule: MaxLengthRule
 *
 * Ensures that a string does not exceed a given number of characters.
 */
final readonly class MaxLengthRule extends AbstractLengthRule
{
    public function __construct(
        private int $max,
        private string $code = 'too_long',
        private string $message = 'The field "{field}" must not exceed {max} characters.',
        bool $multibyte = true,
        string $encoding = 'UTF-8',
    ) {
        parent::__construct($multibyte, $encoding);
    }
    
    #[\Override]
    protected function validate(mixed $value, FieldDefinition $field): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        
        $len = $this->getLength($value);
        if ($len > $this->max) {
            return [
                $this->makeError($field, $this->code, $this->message, [
                    'max' => $this->max,
                    'length' => $len,
                ]),
            ];
        }
        
        return [];
    }
}
