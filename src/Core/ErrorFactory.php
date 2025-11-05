<?php

declare(strict_types=1);

namespace FormToEmail\Core;

/**
 * Factory and normalizer for {@see ErrorDefinition} instances.
 *
 * Converts any supported error representation into a normalized, immutable
 * {@see ErrorDefinition} for consistent downstream handling.
 */
final readonly class ErrorFactory
{
    /**
     * Normalize any supported value into an {@see ErrorDefinition}.
     *
     * @param string|array<string,mixed>|ErrorDefinition $raw
     * @param FieldDefinition $field
     *
     * @throws \InvalidArgumentException If the value cannot be normalized.
     */
    public static function normalize(string|array|ErrorDefinition $raw, FieldDefinition $field): ErrorDefinition
    {
        if ($raw instanceof ErrorDefinition) {
            // Return as-is, or bind to field if missing
            return $raw->getField() === null
                ? $raw->forField($field->getName())
                : $raw;
        }
        
        if (is_string($raw)) {
            return new ErrorDefinition(
                code: $raw,
                message: self::autoMessage($raw),
                field: $field->getName()
            );
        }
        
        // At this point, $raw is known to be array<string, mixed>
        /** @var array<string,mixed> $raw */
        return self::fromArray($raw, $field);
    }
    
    /**
     * Builds an ErrorDefinition from an associative array.
     *
     * @param array<string,mixed> $raw
     */
    private static function fromArray(array $raw, FieldDefinition $field): ErrorDefinition
    {
        $code = (string)($raw['code'] ?? 'unknown');
        $message = (string)($raw['message'] ?? self::autoMessage($code));
        $context = is_array($raw['context'] ?? null) ? $raw['context'] : [];
        $fieldName = (string)($raw['field'] ?? $field->getName());
        
        return new ErrorDefinition(
            code: $code,
            message: $message,
            context: $context,
            field: $fieldName
        );
    }
    
    /**
     * Generates a simple human-readable fallback message.
     */
    public static function autoMessage(string $code): string
    {
        return ucfirst(str_replace('_', ' ', $code));
    }
}
