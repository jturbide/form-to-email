<?php

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormDefinition;
use FormToEmail\Enum\FieldRole;
use FormToEmail\Filter\HtmlEscapeFilter;
use FormToEmail\Filter\RemoveEmojiFilter;
use FormToEmail\Filter\RemoveUrlFilter;
use FormToEmail\Filter\SanitizeEmailFilter;
use FormToEmail\Filter\StripTagsFilter;
use FormToEmail\Filter\TrimFilter;
use FormToEmail\Http\FormToEmailController;
use FormToEmail\Mail\PHPMailerAdapter;
use FormToEmail\Rule\EmailRule;
use FormToEmail\Rule\RequiredRule;
use FormToEmail\Transformer\LowercaseTransformer;

require_once __DIR__ . '/../vendor/autoload.php';

$form = new FormDefinition()
    ->add(new FieldDefinition('name', roles: [FieldRole::SenderName], processors: [
        new TrimFilter(),
        new RequiredRule(),
    ]))
    ->add(new FieldDefinition('email', roles: [FieldRole::SenderEmail], processors: [
        new SanitizeEmailFilter(),
        new EmailRule(),
        new LowercaseTransformer(),
    ]))
    ->add(new FieldDefinition('message', roles: [FieldRole::Body], processors: [
        new RemoveUrlFilter(),
        new RemoveEmojiFilter(),
        new StripTagsFilter(),
        new RequiredRule(),
        new HtmlEscapeFilter(),
    ]));

$mailer = new PHPMailerAdapter(
    useSmtp: true,
    host: 'mail.example.com',
    username: 'no-reply@example.com',
    password: 'secret',
    fromEmail: 'no-reply@example.com',
    fromName: 'Website Contact Form'
);

new FormToEmailController($form, $mailer, ['contact@example.com'])->handle();