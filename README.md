# form-to-email

**A lightweight, extensible PHP 8.4+ library for secure form processing, validation, sanitization, transformation, and structured email delivery.**
Built for modern PHP projects with strict typing, predictable pipelines, and framework-agnostic design.

---

## ✨ Core features

✅ **Field definitions & validation**

* Multiple validators per field (regex, callable, or built-in rules)
* Rich per-field error arrays for frontend UX
* Enum-based field roles (`SenderEmail`, `Subject`, `Body`, etc.)
* Composable rules: `RequiredRule`, `EmailRule`, `RegexRule`, `LengthRule`, etc.

✅ **Processing pipeline (Filters → Transformers → Validators)**

* Unified `Processor` architecture combining filters, transformers, and rules
* Automatic execution in the correct order: **sanitize → transform → validate**
* Full per-field customization, reusable across forms
* Supports pipeline chaining and early bailout on invalid data
* Provides consistent and deterministic behavior between validation runs

✅ **Filters & sanitizers**

* First-class sanitization layer (stackable filters)
* Includes advanced built-ins:

    * `SanitizeEmailFilter` (RFC 6531/5321 aware, strict/relaxed, IDN-safe)
    * `RemoveEmojiFilter` (emoji + pictographic cleanup)
    * `NormalizeNewlinesFilter` (consistent `\n` normalization, optional trimming)
    * `HtmlEscapeFilter` (safe HTML output for templates)
    * `RemoveUrlFilter` (aggressive URL detection/removal)
    * `CallbackFilter` (custom per-field logic, `callable(mixed, FieldDefinition): mixed`)

✅ **Transformers**

* Modify values after sanitization but before validation
* Examples: trimming, lowercasing, formatting, slugifying, normalizing phone numbers
* Easily composable via the same pipeline system

✅ **Email composition**

* Dual HTML + plain-text templates (customizable)
* PHPMailer adapter (default) — easily replaceable with custom adapters
* Enum-based structured responses (e.g. `ResponseCode::Success`, `ResponseCode::ValidationError`)

✅ **Architecture**

* Framework-agnostic — works with plain PHP, Symfony, Laravel, or any custom stack
* 100 % typed, static-analysis-clean (Psalm / PHPStan / Qodana / Sonar)
* Complete PHPUnit coverage with 180+ tests (filters, transformers, rules, adapters)

---

## 🚀 Quick start

```bash
composer require jturbide/form-to-email
```

Example usage:

```php
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormDefinition;
use FormToEmail\Enum\FieldRole;
use FormToEmail\Http\FormToEmailController;
use FormToEmail\Mail\PHPMailerAdapter;
use FormToEmail\Rule\RequiredRule;
use FormToEmail\Rule\EmailRule;
use FormToEmail\Filter\SanitizeEmailFilter;
use FormToEmail\Transformer\TrimTransformer;

$form = new FormDefinition()
  ->add((new FieldDefinition('name', [FieldRole::SenderName]))
    ->addFilter(new TrimTransformer())
    ->addRule(new RequiredRule()))
  ->add((new FieldDefinition('email', [FieldRole::SenderEmail]))
    ->addFilter(new SanitizeEmailFilter(strict: true))
    ->addRule(new RequiredRule())
    ->addRule(new EmailRule()))
  ->add((new FieldDefinition('message', [FieldRole::Body]))
    ->addRule(new RequiredRule()));

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

---

## 🧩 Advanced configuration

### Processor Order

Every field uses a predictable execution order:

```
Processors: Filters, Transformers, Rules (Validators)
```

You can define the order explicitly:
```php
$field = new FieldDefinition('username', [], [
  new HtmlEscapeFilter(),
  new TrimTransformer(),
  new LowercaseTransformer(),
  new RegexRule('/^[a-z0-9_]{3,20}$/')
]);
```

You can combine them freely:
```php
$field = new FieldDefinition('username')
$field->addFilter(new HtmlEscapeFilter());
$field->addTransformer(fn($v) => strtolower(trim($v)));
$field->addRule(new RegexRule('/^[a-z0-9_]{3,20}$/'));
```

You can also chain them:
```php
$field = new FieldDefinition('username')
    ->addFilter(new HtmlEscapeFilter());
    ->addTransformer(fn($v) => strtolower(trim($v)));
    ->addRule(new RegexRule('/^[a-z0-9_]{3,20}$/'));
```

### Built-in Filters

* `SanitizeEmailFilter(strict: bool, normalizeIdn: bool, normalizeCase: bool)`
* `NormalizeNewlinesFilter(trimTrailing: bool = true)`
* `RemoveUrlFilter(aggressive: bool = true)`
* `CallbackFilter(callable $callback)`

### Built-in Transformers

* `TrimTransformer()` — removes leading/trailing spaces
* `LowercaseTransformer()` — converts to lowercase safely
* `UcFirstTransformer()` — capitalizes first letter
* `SlugifyTransformer()` — converts strings to URL-safe slugs

You can also stack your own custom ones:

```php
$field->addTransformer(fn($v) => preg_replace('/\s+/', ' ', $v));
```

---

## 🧠 Future roadmap

* [x] Add sanitization filters ✅
* [x] Add data transformers ✅
* [x] Introduce unified processor pipeline ✅
* [x] Add extensive unit test coverage ✅
* [ ] Add spam protection / honeypot
* [ ] Add submission logging
* [ ] Add sender confirmation & double opt-in
* [ ] Support file attachments
* [ ] Add reCAPTCHA v3 integration
* [ ] Add webhook + API notifications
* [ ] Add rate limiting & IP throttling
* [ ] Add more Mailer adapters (e.g. Symfony, AWS SES, Postmark)

---

## 🧪 Quality assurance

* 100 % typed (`declare(strict_types=1)` everywhere)
* PHP 8.4 features (readonly, typed properties, attributes)
* Fully CI-tested (GitHub Actions + Psalm + PHPStan + PHPUnit)
* Unit coverage for all filters, transformers, rules, and adapters
* Deterministic processing order with detailed test coverage

---

## 🪪 License

**BSD 3-Clause License © Julien Turbide**
