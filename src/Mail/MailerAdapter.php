<?php

declare(strict_types = 1);

namespace FormToEmail\Mail;

/**
 * Interface: MailerAdapter
 *
 * Defines a contract for sending emails.
 *
 * Implementations are responsible for translating a
 * {@see MailPayload} into a real email using any transport
 * mechanism (PHPMailer, Symfony Mailer, Postmark API, etc.).
 *
 * This abstraction allows the rest of the system to remain
 * completely agnostic of the underlying mailer.
 *
 * Example implementations:
 * - {@see PHPMailerAdapter}
 * - SymfonyMailerAdapter
 * - SendmailAdapter
 * - FakeMailerAdapter (for tests)
 *
 * Example usage:
 * ```php
 * $payload = new MailPayload(
 *     to: ['support@company.com'],
 *     subject: 'Contact request',
 *     htmlBody: '<p>Hello</p>',
 *     textBody: 'Hello'
 * );
 *
 * $mailer->send($payload);
 * ```
 */
interface MailerAdapter
{
    /**
     * Sends the given email payload using the underlying transport.
     *
     * Implementations should throw exceptions on failure so that
     * the controller or higher layers can handle them consistently.
     *
     * @throws \Throwable If sending fails.
     */
    public function send(MailPayload $payload): void;
}
