<?php

declare(strict_types=1);

namespace FormToEmail\Enum;

/**
 * Enum: FieldRole
 *
 * Defines semantic roles that can be assigned to form fields.
 * These roles help the library understand how certain values
 * should influence email metadata or content.
 *
 * For example:
 * - A field with the `SenderEmail` role automatically populates
 *   the email "Reply-To" header.
 * - A `Subject` role contributes to the message subject line.
 * - A `Body` role marks a primary message field (e.g., "message").
 *
 * You can assign multiple roles to a single field if necessary
 * (for instance, a "full_name" field might also act as SenderName).
 */
enum FieldRole: string
{
    /**
     * The sender’s email address (used for Reply-To).
     */
    case SenderEmail = 'sender_email';
    
    /**
     * The sender’s display name (used for Reply-To name).
     */
    case SenderName = 'sender_name';
    
    /**
     * Field contributing to the email subject line.
     */
    case Subject = 'subject';
    
    /**
     * Field containing the main message body (content).
     */
    case Body = 'body';
}
