<?php

declare(strict_types=1);

/**
 * form-to-email.php
 *
 * A standalone example and entrypoint for the Form-to-Email library.
 *
 * This script defines a form schema, initializes the PHPMailerAdapter,
 * and runs the FormToEmailController to handle incoming POST requests.
 *
 * It can be placed directly on a server or used as a template for
 * framework integration.
 */

use FormToEmail\Core\FormDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Validation\Rules\RequiredRule;
use FormToEmail\Validation\Rules\EmailRule;
use FormToEmail\Validation\Rules\LengthRule;
use FormToEmail\Validation\Rules\RegexRule;
use FormToEmail\Validation\Rules\CallbackRule;
use FormToEmail\Enum\FieldRole;
use FormToEmail\Http\FormToEmailController;
use FormToEmail\Mail\PHPMailerAdapter;

// --- Autoload dependencies ---
require __DIR__ . '/../vendor/autoload.php';

// --- Define form schema ---
$form = new FormDefinition()
    ->add(new FieldDefinition(
        name: 'name',
        roles: [FieldRole::SenderName],
        rules: [
            new RequiredRule(),
            new LengthRule(min: 2, max: 100),
            new RegexRule('/^[\p{L}\s\-\'\.]+$/u', 'invalid_name')
        ],
        sanitizer: static fn(string $v) => htmlspecialchars(strip_tags($v), ENT_QUOTES, 'UTF-8')
    ))
    ->add(new FieldDefinition(
        name: 'email',
        roles: [FieldRole::SenderEmail],
        rules: [
            new RequiredRule(),
            new EmailRule(),
        ],
        sanitizer: static fn(string $v): string => filter_var($v, FILTER_SANITIZE_EMAIL) ?: '',
    ))
    ->add(new FieldDefinition(
        name: 'subject',
        roles: [FieldRole::Subject],
        rules: [new LengthRule(max: 200)],
        sanitizer: static fn(string $v) => htmlspecialchars(strip_tags($v), ENT_QUOTES, 'UTF-8')
    ))
    ->add(new FieldDefinition(
        name: 'message',
        roles: [FieldRole::Body],
        rules: [
            new RequiredRule(),
            new LengthRule(min: 10, max: 5000)
        ],
        sanitizer: static fn(string $v) => htmlspecialchars(strip_tags($v), ENT_QUOTES, 'UTF-8')
    ))
    ->add(new FieldDefinition(
        name: 'phone',
        rules: [
            new CallbackRule(static function (string $v): array {
                if ($v === '') {
                    return [];
                }
                return preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $v)
                    ? []
                    : ['invalid_phone'];
            }),
        ],
        sanitizer: static fn(string $v) => preg_replace('/[^\d\+\-\(\) ]/', '', $v) ?? ''
    ));

// --- Configure mail transport ---
$mailer = new PHPMailerAdapter(
    useSmtp: true,
    host: $_ENV['SMTP_HOST']     ?? 'mail.example.com',
    port: (int)($_ENV['SMTP_PORT'] ?? 465),
    username: $_ENV['SMTP_USER'] ?? 'no-reply@example.com',
    password: $_ENV['SMTP_PASS'] ?? '',
    encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'ssl',
    auth: (bool)($_ENV['SMTP_AUTH'] ?? true),
    debug: (int)($_ENV['SMTP_DEBUG'] ?? 0),
    fromEmail: $_ENV['FROM_EMAIL'] ?? 'no-reply@example.com',
    fromName: $_ENV['FROM_NAME'] ?? 'Website Contact Form'
);

// --- Initialize and run controller ---
$controller = new FormToEmailController(
    form: $form,
    mailer: $mailer,
    recipients: explode(',', $_ENV['CONTACT_RECIPIENTS'] ?? 'contact@example.com'),
    defaultSubject: $_ENV['DEFAULT_SUBJECT'] ?? 'New Form Submission'
);

// Handle request and output structured JSON
$controller->handle();
