<?php

declare(strict_types=1);

namespace FormToEmail\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;

/**
 * Rule: EmailRule
 *
 * Validates email addresses according to RFC 5321 and RFC 6531 (UTF-8 in local parts).
 * Provides IDN (Punycode) domain normalization and optional relaxed Unicode mode.
 *
 * Empty values are considered valid; combine with {@see RequiredRule} for mandatory fields.
 *
 * Features:
 * - Normalizes IDN domains using `idn_to_ascii()`
 * - Supports Unicode local-parts (RFC 6531)
 * - Validates with PHP's `filter_var()` (for baseline RFC 5321 syntax)
 * - Returns structured {@see ErrorDefinition} on failure
 */
final readonly class EmailRule extends AbstractRule
{
    public function __construct(
        private string $error = 'invalid_email',
        /** Whether to allow Unicode characters in the local part (RFC 6531) */
        private bool $allowUnicode = true,
        /** Whether to normalize IDN domains to ASCII (Punycode) before validation */
        private bool $normalizeIdn = true,
    ) {
    }
    
    #[\Override]
    public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
    {
        // Skip validation for empty / non-string inputs (handled by RequiredRule)
        if (!is_string($value) || $value === '') {
            return $value;
        }
        
        $normalized = $this->normalizeEmail($value);
        
        if ($normalized === null || !$this->isValidEmail($normalized)) {
            $context->addError(
                $field->getName(),
                new ErrorDefinition(
                    code: $this->error,
                    message: 'Invalid email format.',
                    field: $field->getName(),
                    context: [
                        'value' => $value,
                        'normalized' => $normalized,
                    ]
                )
            );
        }
        
        return $value;
    }
    
    /**
     * Normalize Unicode domain names to ASCII using Punycode.
     */
    private function normalizeEmail(string $email): ?string
    {
        if (!str_contains($email, '@')) {
            return null;
        }
        
        // Always ensure both local and domain keys exist
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        
        // Guard against missing domain part
        if ($domain === '') {
            return null;
        }
        
        // Normalize domain part (IDN → ASCII)
        if ($this->normalizeIdn && function_exists('idn_to_ascii')) {
            try {
                $asciiDomain = @idn_to_ascii(
                    $domain,
                    IDNA_DEFAULT,
                    defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0
                );
                if ($asciiDomain !== false) {
                    $domain = $asciiDomain;
                }
            } catch (\ValueError) {
                // occurs if domain is invalid (e.g. empty or bad chars)
                return null;
            }
        }
        
        return "{$local}@{$domain}";
    }
    
    /**
     * Validate email format according to RFC 5321 / RFC 6531.
     */
    private function isValidEmail(string $email): bool
    {
        // First try standard PHP filter
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        
        // RFC 6531 Unicode local-part support (relaxed mode)
        if ($this->allowUnicode) {
            $pattern = '/^[\p{L}\p{N}\p{M}\p{Pc}\p{Pd}!#$%&\'*+\/=?^_`{|}~."]+@[\p{L}\p{N}\p{M}\.\-]+$/u';
            return (bool)preg_match($pattern, $email);
        }
        
        return false;
    }
}
