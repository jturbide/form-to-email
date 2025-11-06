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

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormDefinition;
use FormToEmail\Core\Logger;
use FormToEmail\Enum\FieldRole;
use FormToEmail\Enum\LogFormat;
use FormToEmail\Filter\HtmlEscapeFilter;
use FormToEmail\Filter\SanitizeEmailFilter;
use FormToEmail\Filter\SanitizePhoneFilter;
use FormToEmail\Filter\StripTagsFilter;
use FormToEmail\Http\FormToEmailController;
use FormToEmail\Mail\PHPMailerAdapter;
use FormToEmail\Rule\CallbackRule;
use FormToEmail\Rule\EmailRule;
use FormToEmail\Rule\LengthRule;
use FormToEmail\Rule\RegexRule;
use FormToEmail\Rule\RequiredRule;


// --- Autoload dependencies ---
require __DIR__ . '/../vendor/autoload.php';

// --- Define form schema ---
$form = new FormDefinition()
    ->add(new FieldDefinition(
        name: 'name',
        roles: [FieldRole::SenderName],
        processors: [
            new RequiredRule(),
            new LengthRule(min: 2, max: 100),
            new RegexRule('/^[\p{L}\s\-\'\.]+$/u', 'invalid_name'),
            new StripTagsFilter(),
            new HtmlEscapeFilter(),
        ],
    ))
    ->add(new FieldDefinition(
        name: 'email',
        roles: [FieldRole::SenderEmail],
        processors: [
            new RequiredRule(),
            new EmailRule(),
            new SanitizeEmailFilter(),
        ],
    ))
    ->add(new FieldDefinition(
        name: 'subject',
        roles: [FieldRole::Subject],
        processors: [
            new LengthRule(max: 200),
            new StripTagsFilter(),
            new HtmlEscapeFilter(),
        ],
    ))
    ->add(new FieldDefinition(
        name: 'message',
        roles: [FieldRole::Body],
        processors: [
            new RequiredRule(),
            new LengthRule(min: 10, max: 5000),
            new StripTagsFilter(),
            new HtmlEscapeFilter(),
        ],
    ))
    ->add(new FieldDefinition(
        name: 'phone',
        processors: [
            new CallbackRule(static function (string $v): array {
                if ($v === '') {
                    return [];
                }
                return preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $v)
                    ? []
                    : ['invalid_phone'];
            }),
            new SanitizePhoneFilter()
        ],
    ));

// --- Configure mail transport ---
$mailer = new PHPMailerAdapter(
    useSmtp: true,
    host: $_ENV['SMTP_HOST'] ?? 'mail.example.com',
    port: (int)($_ENV['SMTP_PORT'] ?? 465),
    username: $_ENV['SMTP_USER'] ?? 'no-reply@example.com',
    password: $_ENV['SMTP_PASS'] ?? '',
    encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'ssl',
    auth: (bool)($_ENV['SMTP_AUTH'] ?? true),
    debug: (int)($_ENV['SMTP_DEBUG'] ?? 0),
    fromEmail: $_ENV['FROM_EMAIL'] ?? 'no-reply@example.com',
    fromName: $_ENV['FROM_NAME'] ?? 'Website Contact Form'
);

// --- Configure logger ---
$logger = new Logger(
    enabled: (bool)($_ENV['LOGGER_ENABLED'] ?? false),
    format: LogFormat::Text,
    file: $_ENV['LOGGER_FILE'] ?? __DIR__ . '/../logs/form.log',
);

// --- Initialize and run controller ---
$controller = new FormToEmailController(
    form: $form,
    mailer: $mailer,
    recipients: explode(',', $_ENV['CONTACT_RECIPIENTS'] ?? 'contact@example.com'),
    defaultSubject: $_ENV['DEFAULT_SUBJECT'] ?? 'New Form Submission',
    logger: $logger,
);

// Handle request and output structured JSON
$controller->handle();
