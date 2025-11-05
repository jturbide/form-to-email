<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\FieldDefinition;

abstract class AbstractRule implements Rule
{
    public function validate(string $value, FieldDefinition $field): array
    {
        return [];
    }
    
    public function process(mixed $value, FieldDefinition $field, array &$errors): mixed
    {
        $fieldErrors = $this->validate($value, $field);
        
        if (count($fieldErrors)) {
            $errors = $fieldErrors;
        }
        
        return $value;
    }
}
