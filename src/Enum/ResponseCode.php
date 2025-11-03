<?php

declare(strict_types = 1);

namespace FormToEmail\Enum;

/**
 * Enum: ResponseCode
 *
 * Defines standardized, language-agnostic response codes returned
 * by the library when processing a form submission.
 *
 * These codes are intended to be stable identifiers that frontends
 * can map to localized messages or UI behaviors (e.g., toast, alert, etc.).
 *
 * Each code corresponds to a specific high-level result — regardless
 * of transport (HTTP, CLI, etc.). This makes it ideal for both web APIs
 * and console-based form ingestion workflows.
 *
 * Usage example:
 *
 * ```php
 * http_response_code(200);
 * echo json_encode(['code' => ResponseCode::OK->value]);
 * ```
 */
enum ResponseCode: string
{
    /**
     * ✅ Success — the form has been validated and email was sent.
     */
    case OK = 'ok';
    
    /**
     * ❌ Request method is not allowed (only POST, for example).
     */
    case INVALID_METHOD = 'invalid_method';
    
    /**
     * ❌ Malformed or empty JSON payload.
     */
    case INVALID_JSON = 'invalid_json';
    
    /**
     * ⚠️ Validation failed — one or more fields did not pass rules.
     */
    case VALIDATION_ERROR = 'validation_error';
    
    /**
     * 🚨 Mail transport failed — PHPMailer or SMTP error.
     */
    case MAIL_FAILURE = 'mail_failure';
    
    /**
     * 💥 Internal logic or runtime exception occurred.
     */
    case INTERNAL_ERROR = 'internal_error';
}
