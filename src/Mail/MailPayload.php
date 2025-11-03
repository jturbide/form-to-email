<?php

declare(strict_types = 1);

namespace FormToEmail\Mail;

/**
 * Class: MailPayload
 *
 * Immutable value object representing a single email message.
 *
 * This class is passed to a {@see MailerAdapter} implementation,
 * such as PHPMailerAdapter, which handles the actual delivery.
 *
 * It contains:
 * - Recipients (to)
 * - Subject line
 * - HTML and plain-text bodies
 * - Optional Reply-To metadata (for direct responses)
 *
 * Example:
 * ```php
 * $payload = new MailPayload(
 *     to: ['contact@company.com'],
 *     subject: 'New contact form submission',
 *     htmlBody: '<p>Hello</p>',
 *     textBody: 'Hello',
 *     replyToEmail: 'sender@example.com',
 *     replyToName: 'John Doe'
 * );
 * ```
 */
final readonly class MailPayload
{
    /**
     * @param list<string> $to
     *   Destination addresses.
     *
     * @param string $subject
     *   The message subject line.
     *
     * @param string $htmlBody
     *   The formatted HTML body of the email.
     *
     * @param string $textBody
     *   The plain text fallback for non-HTML mail clients.
     *
     * @param string|null $replyToEmail
     *   Optional sender reply-to address.
     *
     * @param string|null $replyToName
     *   Optional sender display name for Reply-To header.
     */
    public function __construct(
        public array $to,
        public string $subject,
        public string $htmlBody,
        public string $textBody,
        public ?string $replyToEmail = null,
        public ?string $replyToName = null,
    ) {}
}
