# Form to Email

[![Build Status](https://github.com/jturbide/form-to-email/actions/workflows/main.yml/badge.svg)](https://github.com/jturbide/form-to-email/actions)
[![Docs](https://img.shields.io/badge/docs-online-success.svg)](https://github.com/jturbide/form-to-email)
[![Downloads](https://img.shields.io/packagist/dt/jturbide/form-to-email?color=blue&label=downloads)](https://packagist.org/packages/jturbide/form-to-email)

[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](https://github.com/jturbide/form-to-email)
[![Psalm Level](https://img.shields.io/badge/psalm-level%202-brightgreen.svg)](https://psalm.dev/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-brightgreen)](https://phpstan.org/)
[![Code Style](https://img.shields.io/badge/code%20style-PSR--12-blue)](https://www.php-fig.org/psr/psr-12/)

**A lightweight, extensible PHP 8.4+ library for secure form processing, validation, sanitization, transformation, and structured email delivery.**
Built for modern PHP projects with strict typing, predictable pipelines, and framework-agnostic design.

---

## 🚀 Quick start (tl;dr)

Install the package:
```bash
composer require jturbide/form-to-email
```

Create a form definition, create a mailer, and handle a request:
```php
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
```

Serve the file
```bash
php -s localhost:8000 ./
```

Send a XHR request
```bash
curl -X POST -H "Content-Type: application/json" -d '{
  "name": "Julien",
  "email": "",
  "message": "Hello world!"
}' http://localhost:8000/contact.php
```

See the result
```json

```

---

## ✨ Core Features

✅ **Field Definitions & Validation**

* Multiple validators per field (`RegexRule`, `CallbackRule`, `EmailRule`, etc.)
* Rich per-field error arrays for enhanced frontend UX
* Enum-based field roles (`SenderEmail`, `Subject`, `Body`, etc.)
* Composable and reusable rules (`RequiredRule`, `LengthRule`, `RegexRule`, etc.)

✅ **Unified Processor Pipeline (Filters, Transformers, Validators)**

* Single `FieldProcessor` interface powering all processing stages
* Deterministic execution order: **sanitize → validate → transform**
* Configurable per-field pipeline with early bailout support
* Fully reusable across forms for consistent data behavior

✅ **Filters & Sanitizers**

* First-class sanitization layer with composable filters
* Advanced built-in filters:
    * `SanitizeEmailFilter` — RFC 6531/5321 aware, IDN-safe
    * `RemoveEmojiFilter` — removes emoji and pictographic symbols
    * `NormalizeNewlinesFilter` — consistent `\n` normalization
    * `HtmlEscapeFilter` — secure HTML output for templates
    * `RemoveUrlFilter` — aggressively removes embedded URLs
    * `CallbackFilter` — supports custom callable filters

✅ **Transformers**

* Mutate values post-sanitization, pre-validation
* Includes built-ins: `LowercaseTransformer`, `CallbackTransformer`, etc.
* Ideal for formatting, slugifying, or normalizing input values

✅ **Email Composition & Delivery**

* Dual HTML and plain-text templates (fully customizable)
* `PHPMailerAdapter` by default — pluggable architecture for other adapters
* Enum-based structured responses (`ResponseCode::Success`, `ResponseCode::ValidationError`, etc.)
* Automatic field-role mapping to email metadata

✅ **Logging & Observability**

* Built-in `Logger` for form submission tracking
* Supports multiple formats: raw text, JSON, and syslog-compatible
* Optional toggles for successful or failed submission logging
* Fully tested with configurable verbosity and custom formatters

✅ **Architecture & Quality**

* Framework-agnostic — works with plain PHP, Symfony, Laravel, or any custom stack
* 100 % typed and static-analysis-clean (Psalm / PHPStan / Qodana / SonarQube)
* 420+ PHPUnit tests with full coverage (core, filters, transformers, rules, adapters, logger)
* Predictable and deterministic pipeline ensuring consistent validation behavior



---

## 🧩 Advanced Configuration

### Built-in Adapters

* **`MailerAdapter` (interface)** — minimal contract for sending mail.
* **`PHPMailerAdapter`** — default adapter backed by PHPMailer.
* **`MailPayload`** — immutable value object that carries `to`, `subject`, `htmlBody`, `textBody`, `replyTo*`.

> Swap adapters by implementing `MailerAdapter` and injecting your implementation into `FormToEmailController`.

---

### Built-in Filters

All filters implement `Filter` and the unified `FieldProcessor` contract. Use them to **sanitize** or **normalize** raw input.

* **`TrimFilter`** — trims leading/trailing whitespace.
* **`StripTagsFilter`** — removes HTML tags.
* **`HtmlEscapeFilter`** — escapes HTML for safe rendering.
* **`SanitizeTextFilter`** — general text cleanup (safe subset).
* **`SanitizeEmailFilter`** — RFC 5321/6531 aware, IDN-safe normalization.
* **`SanitizePhoneFilter`** — digits-first cleanup for phone inputs.
* **`NormalizeNewlinesFilter`** — converts mixed newlines to `\n`.
* **`RemoveUrlFilter`** — removes URLs aggressively.
* **`RemoveEmojiFilter`** — strips emoji and pictographs.
* **`CallbackFilter`** — custom callable filter per field.
* ...more to come!

> Tip: Prefer **filters** early to make downstream validation predictable.

---

### Built-in Rules (Validators)

Rules implement `Rule` (and `FieldProcessor`) and add `ValidationError` entries when constraints fail.

* **`RequiredRule`** — value must be present/non-empty.
* **`EmailRule`** — email syntax validation (works with IDN-normalized values).
* **`RegexRule`** — arbitrary pattern checks.
* **`LengthRule` / `MinLengthRule` / `MaxLengthRule`** — string length constraints.
* **`CallbackRule`** — custom boolean/callback validation.
* ...more to come!

> Errors use `ErrorDefinition` with machine-readable `code`, `message`, and `context`.

---

### Built-in Transformers

Transformers modify values **after sanitization** but generally **before rules**.

* **`LowercaseTransformer`** — lowercases strings.
* **`UcFirstTransformer`** — capitalizes first letter
* **`CallbackTransformer`** — custom mapping logic.
* ...more to come!

> Keep formatting here (slugify, case changes, phone canonicalization) so rules validate the final shape.

---

### Processor Order

Execution is **exactly the order you define** in each `FieldDefinition`. A good default convention is:

```
Filters → Rules (Validators) → Transformers
```

**Explicit order example**

```php
$field = new FieldDefinition('username', roles: [], processors: [
    new HtmlEscapeFilter(),
    new TrimFilter(),
    new LowercaseTransformer(),
    new RegexRule('/^[a-z0-9_]{3,20}$/'),
]);
```

**Incremental API**

```php
$field = new FieldDefinition('username');
$field->addFilter(new HtmlEscapeFilter());
$field->addFilter(new TrimFilter());
$field->addTransformer(new LowercaseTransformer());
$field->addRule(new RegexRule('/^[a-z0-9_]{3,20}$/'));
```

**Chaining**

```php
$field = (new FieldDefinition('username'))
    ->addFilter(new HtmlEscapeFilter())
    ->addFilter(new TrimFilter())
    ->addTransformer(new LowercaseTransformer())
    ->addRule(new RegexRule('/^[a-z0-9_]{3,20}$/'));
```

> The library does not reorder processors for you. Define the pipeline you want. As a rule of thumb: **sanitize first, validate, then transform to final form.**

---

### Custom Processors (Advanced)

Implement the unified processor contract to add your own behavior:

```php
use FormToEmail\Core\{FieldProcessor, FieldDefinition, FormContext};

final class SlugifyTransformer implements FieldProcessor {
    public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed {
        $s = strtolower(trim((string)$value));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }
}
```

Register it like any other processor:

```php
$field = (new FieldDefinition('title'))
    ->addTransformer(new SlugifyTransformer());
```

You can also stack your own custom ones:

```php
$field->addTransformer(fn($v) => preg_replace('/\s+/', ' ', $v));
```

---

## 🧭 Future Roadmap

This library already covers the essentials for form validation, sanitization, transformation, and email delivery.
However, there’s still plenty of room for evolution (as always). While most of the following items aren’t priorities for my own use cases, I’m open to implementing them if they’re valuable to you or your project.

### ✅ Completed
- [x] Sanitization filters ✅
- [x] Data transformers ✅
- [x] Unified processor pipeline ✅
- [x] Comprehensive unit test coverage ✅
- [x] Submission logging system ✅

### 🧠 Planned / Proposed
- [ ] reCAPTCHA v3 integration — prevent bot submissions without user friction
- [ ] File attachments — safely handle uploaded files via configurable limits
- [ ] Sender confirmation & double opt-in — ensure sender authenticity before sending
- [ ] Spam protection / honeypot — lightweight anti-spam defense
- [ ] Webhook + API notifications — trigger external systems on successful submissions
- [ ] Rate limiting & IP throttling — basic abuse protection for public endpoints
- [ ] Additional mailer adapters — e.g. Symfony Mailer, AWS SES, Postmark
- [ ] SmartEmailRule — enhanced email validation with MX/DNS deliverability checks

### 💡 Want a feature sooner?
Open a GitHub issue or start a discussion — contributions and ideas are always welcome!

---

## 🧪 Quality Assurance

This library is built for reliability, maintainability, and modern PHP ecosystems.

* **100 % strictly typed** — every file uses `declare(strict_types=1)`
* **100 % code coverage** — verified through both unit and integration tests
* **Modern PHP 8.4 syntax** — `readonly` classes, typed properties, attributes, and enhanced type safety
* **Continuous Integration** — fully validated via GitHub Actions with PHPUnit, Psalm, and PHPStan
* **Comprehensive test coverage** — unit-tested filters, transformers, rules, and mail adapters
* **Deterministic pipeline** — predictable processor order with verified behavior across all test cases

---

## 🪪 License

**BSD 3-Clause License © Julien Turbide**
