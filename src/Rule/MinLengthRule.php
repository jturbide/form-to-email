<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\FieldDefinition;

/**
 * Rule: MinLengthRule
 *
 * Ensures that a string is at least a given number of characters long.
 */
final readonly class MinLengthRule extends AbstractLengthRule
{
    public function __construct(
        private int $min,
        private string $code = 'too_short',
        private string $message = 'The field "{field}" must be at least {min} characters long.',
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
        if ($len < $this->min) {
            return [
                $this->makeError($field, $this->code, $this->message, [
                    'min' => $this->min,
                    'length' => $len,
                ]),
            ];
        }
        
        return [];
    }
}
