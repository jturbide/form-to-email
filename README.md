# form-to-emailmin

**A lightweight PHP 8.4+ library to process form submissions, validate fields, and send structured email notifications with configurable templates and error codes.**

---

## ✨ Features
- Multiple validators per field (regex, callback, built-in)
- Per-field error arrays for rich frontend UX
- Semantic field roles (e.g. SenderEmail, Subject, Body)
- Default HTML + text email templates
- PHPMailer adapter (easily swappable)
- Enum-based structured API responses
- Framework-agnostic (works in plain PHP, Symfony, Laravel, etc.)

---

## 🚀 Quick start

```bash
composer require jturbide/form-to-email
```

Then define a simple form schema:

```php
use FormToEmail\Core\FormDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Validation\Rules\RequiredRule;
use FormToEmail\Validation\Rules\EmailRule;
use FormToEmail\Enum\FieldRole;
use FormToEmail\Mail\PHPMailerAdapter;
use FormToEmail\Http\FormToEmailController;

$form = (new FormDefinition())
  ->add(new FieldDefinition('name', [FieldRole::SenderName], [new RequiredRule()]))
  ->add(new FieldDefinition('email', [FieldRole::SenderEmail], [new RequiredRule(), new EmailRule()]))
  ->add(new FieldDefinition('message', [FieldRole::Body], [new RequiredRule()]));

$mailer = new PHPMailerAdapter(
  useSmtp: true,
  host: 'mail.example.com',
  username: 'no-reply@example.com',
  password: 'secret',
  fromEmail: 'no-reply@example.com',
  fromName: 'Website Contact Form'
);

(new FormToEmailController($form, $mailer, ['contact@example.com']))->handle();
```

## 🧠 Future roadmap

- Sender confirmation support
- File upload attachments
- reCAPTCHA integration
- Webhook + API notifications
- Rate limiting + IP throttling

## 🪪 License

BSD 3-Clause License © Julien Turbide