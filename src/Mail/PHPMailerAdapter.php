<?php

declare(strict_types=1);

namespace FormToEmail\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Class: PHPMailerAdapter
 *
 * Provides a robust implementation of {@see MailerAdapter} using
 * the popular PHPMailer library for message delivery.
 *
 * This adapter supports both SMTP and the default PHP mail()
 * transport, depending on configuration.
 *
 * Example:
 * ```php
 * $mailer = new PHPMailerAdapter(
 *     useSmtp: true,
 *     host: 'mail.example.com',
 *     port: 465,
 *     username: 'no-reply@example.com',
 *     password: 'secret',
 *     encryption: 'ssl',
 *     auth: true,
 *     fromEmail: 'no-reply@example.com',
 *     fromName: 'Website Contact Form'
 * );
 *
 * $mailer->send($payload);
 * ```
 */
final class PHPMailerAdapter implements MailerAdapter
{
    public function __construct(
        /**
         * Whether to use SMTP (true) or PHP mail() (false).
         */
        private readonly bool $useSmtp = true,
        /**
         * SMTP server hostname.
         */
        private readonly string $host = 'localhost',
        /**
         * SMTP port (25, 465, or 587 are common).
         */
        private readonly int $port = 25,
        /**
         * SMTP username for authentication (if enabled).
         */
        private readonly string $username = '',
        /**
         * SMTP password for authentication (if enabled).
         */
        private readonly string $password = '',
        /**
         * Encryption mode: '', 'ssl', or 'tls'.
         */
        private readonly string $encryption = '',
        /**
         * Whether SMTP authentication is required.
         */
        private readonly bool $auth = false,
        /**
         * PHPMailer debug level: 0 (off), 2 (verbose).
         */
        private readonly int $debug = 0,
        /**
         * The "From" address for all outgoing messages.
         * If null, the adapter will use the reply-to email instead.
         */
        private readonly ?string $fromEmail = null,
        /**
         * The display name associated with the "From" address.
         */
        private readonly string $fromName = 'Form Notification',
    ) {
    }
    
    /**
     * @inheritDoc
     */
    #[\Override]
    public function send(MailPayload $payload): void
    {
        $mail = new PHPMailer(true);
        
        try {
            // --- Setup transport
            if ($this->useSmtp) {
                $mail->isSMTP();
                $mail->Host       = $this->host;
                $mail->Port       = $this->port;
                $mail->SMTPAuth   = $this->auth;
                $mail->Username   = $this->username;
                $mail->Password   = $this->password;
                $mail->SMTPSecure = $this->encryption ?: PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPDebug  = $this->debug;
            }
            
            // --- Sender & recipients
            $fromAddress = $this->fromEmail ?? $payload->replyToEmail ?? 'no-reply@example.com';
            $mail->setFrom($fromAddress, $this->fromName);
            
            // Reply-To metadata
            if ($payload->replyToEmail !== null && $payload->replyToEmail !== '') {
                $mail->addReplyTo($payload->replyToEmail, $payload->replyToName ?? '');
            }
            
            // Add recipients
            foreach ($payload->to as $recipient) {
                $mail->addAddress($recipient);
            }
            
            // --- Message content
            $mail->isHTML(true);
            $mail->Subject = $payload->subject;
            $mail->Body    = $payload->htmlBody;
            $mail->AltBody = $payload->textBody;
            
            // --- Send
            if (!$mail->send()) {
                throw new \RuntimeException('PHPMailer send() returned false without exception.');
            }
        } catch (PHPMailerException $e) {
            throw new \RuntimeException(
                sprintf('PHPMailer error: %s', $e->getMessage()),
                previous: $e
            );
        }
    }
}
