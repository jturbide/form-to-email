<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\FieldDefinition;

abstract class AbstractRule implements Rule
{
    #[\Override]
    public function validate(mixed $value, FieldDefinition $field): array
    {
        return [];
    }
    
    #[\Override]
    public function process(mixed $value, FieldDefinition $field, array &$errors): mixed
    {
        $fieldErrors = $this->validate($value, $field);
        
        if (count($fieldErrors)) {
            $errors = $fieldErrors;
        }
        
        return $value;
    }
}
