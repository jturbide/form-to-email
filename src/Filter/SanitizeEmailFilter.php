<?php

declare(strict_types=1);

namespace FormToEmail\Filter;

use FormToEmail\Core\FieldDefinition;

/**
 * RFC 6531–aware Unicode email sanitizer.
 *
 * Strict mode → ASCII-only (RFC 5321)
 * Relaxed mode → Unicode local-part (RFC 6531)
 *
 * Features:
 * - Removes emoji, invisible, and control chars
 * - Prevents header injection (even encoded)
 * - Collapses multiple dots/@ symbols
 * - Normalizes IDN → punycode
 * - Preserves valid symbols (+, _, -, ., ')
 */
final class SanitizeEmailFilter extends AbstractFilter
{
    public function __construct(
        private readonly bool $strict = true,
        private readonly bool $normalizeIdn = true,
        private readonly bool $normalizeCase = true,
    ) {
    }
    
    #[\Override]
    public function apply(mixed $value, FieldDefinition $field): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        $email = trim($value);
        if ($email === '') {
            return '';
        }
        
        // 1. Decode encoded CRLF (e.g. %0A)
        $email = str_ireplace(['%0a', '%0d'], "\n", $email);
        
        // 2. Remove invisible control characters
        $email = preg_replace('/[^\P{C}\t\n\r]/u', '', $email) ?? '';
        
        // 3. Remove actual CR/LF
        $email = str_replace(["\r", "\n"], '', $email);
        
        // 4. Strip dangerous header keywords
        $email = preg_replace('/\b(?:cc|bcc|to|from|subject)\s*:\s*/iu', '', $email) ?? '';
        
        // 5. Strip angle brackets
        $email = str_replace(['<', '>'], '', $email);
        
        // 6. Collapse multiple @ → keep first
        if (substr_count($email, '@') > 1) {
            $parts = explode('@', $email);
            $local = array_shift($parts);
            $domain = implode('', $parts);
            $email = "{$local}@{$domain}";
        }
        
        // 7. Split parts safely
        if (!str_contains($email, '@')) {
            return $this->sanitizeLocal($email);
        }
        
        [$local, $domain] = explode('@', $email, 2) + ['', ''];
        
        // 8. Normalize domain case
        if ($this->normalizeCase) {
            $domain = mb_strtolower($domain, 'UTF-8');
        }
        
        // 9. IDN → ASCII safely
        if ($this->normalizeIdn && $domain !== '' && function_exists('idn_to_ascii')) {
            $asciiDomain = @idn_to_ascii(
                $domain,
                IDNA_DEFAULT,
                defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 0
            );
            if ($asciiDomain !== false) {
                $domain = $asciiDomain;
            }
        }
        
        // 10. Sanitize local part
        $local = $this->strict
            ? $this->stripInvalidAscii($local)
            : $this->sanitizeUnicodeLocal($local);
        
        // 11. Collapse double dots and trim
        $local = preg_replace('/\.{2,}/', '.', $local) ?? '';
        $local = trim($local, '. ');
        
        // 12. Recombine sanitized parts
        return trim("{$local}@{$domain}");
    }
    
    private function stripInvalidAscii(string $input): string
    {
        return preg_replace('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~.\-]/u', '', $input) ?? '';
    }
    
    private function sanitizeUnicodeLocal(string $input): string
    {
        $input = preg_replace('/[^\p{L}\p{N}+_.\'\-]/u', '', $input) ?? '';
        $input = preg_replace('/[\x{1F300}-\x{1FAFF}\x{1F1E6}-\x{1F64F}\x{2600}-\x{27BF}]/u', '', $input) ?? '';
        $input = preg_replace('/\.{2,}/', '.', $input) ?? '';
        return trim($input, '. ');
    }
    
    private function sanitizeLocal(string $input): string
    {
        return $this->strict
            ? $this->stripInvalidAscii($input)
            : $this->sanitizeUnicodeLocal($input);
    }
}
