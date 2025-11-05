<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * Class SanitizePhoneFilter
 *
 * Removes any non-digit characters from phone number strings.
 * Keeps only 0–9 digits, ideal for standardized phone storage.
 */
final class SanitizePhoneFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     */
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // Remove all non-digit characters
        $digits = preg_replace('/\D+/', '', $value);
        
        // Trim leading zeros if desired, but generally we keep them
        return $digits ?? '';
    }
}
