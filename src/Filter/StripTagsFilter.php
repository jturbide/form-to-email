<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * Class StripTagsFilter
 *
 * Removes all HTML and PHP tags from string values.
 * Optionally allows a whitelist of permitted tags.
 *
 * Also removes <script> and <style> blocks entirely
 * (including their content), as these are never safe
 * in user-submitted content.
 */
class StripTagsFilter extends AbstractFilter
{
    /**
     * @param string[] $allowedTags List of allowed HTML tags, e.g. ['<b>', '<i>'].
     */
    public function __construct(
        protected readonly array $allowedTags = []
    ) {
    }
    
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // Remove <script> and <style> blocks (with contents)
        $value = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $value);
        
        // Apply built-in strip_tags with allowed list
        return strip_tags($value, implode('', $this->allowedTags));
    }
}
