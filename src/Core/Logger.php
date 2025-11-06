<?php

declare(strict_types=1);

namespace FormToEmail\Core;

use Closure;
use DateTimeImmutable;
use FormToEmail\Enum\LogFormat;
use FormToEmail\Enum\LogLevel;
use Throwable;

/**
 * Class: Logger
 *
 * Lightweight, dependency-free logger dedicated to the FormToEmail lifecycle.
 * - No external packages
 * - Analyzer-friendly (Psalm/PHPStan/Qodana/SonarQube)
 * - Fluent & constructor-based configuration
 * - JSON/Text output, file/syslog/STDERR targets
 *
 * Usage (constructor):
 *   $logger = new Logger(
 *       enabled: true,
 *       format: LogFormat::Json,
 *       file: __DIR__ . '/../logs/form.log',
 *       includeRawInput: false,
 *   );
 *
 * Usage (fluent):
 *   $logger = (new Logger())
 *       ->enable()
 *       ->toFile(__DIR__ . '/../logs/form.log')
 *       ->asJson()
 *       ->includeRawInput(false)
 *       ->enableFailureEvents(true);
 */
final class Logger
{
    // ------------------------------------------------------------
    // Configurable State (intentionally mutable to support chaining)
    // ------------------------------------------------------------
    
    /** Master enable/disable switch. */
    private bool $enabled;
    
    /** Output format (text|json). */
    private LogFormat $format;
    
    /** Write to syslog instead of file/stderr. */
    private bool $useSyslog;
    
    /** Optional file path to append logs to. */
    private ?string $file;
    
    /** Whether to include raw, unfiltered input (may contain PII). */
    private bool $includeRawInput;
    
    /** Whether to log successful submissions. */
    private bool $logSuccessEvents;
    
    /** Whether to log validation/mail failures. */
    private bool $logFailureEvents;
    
    /** Optional custom formatter normalized to Closure(level, message, context, timestamp): string */
    private ?Closure $formatter;
    
    /** JSON flags for robust, safe encoding. */
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR;
    
    /**
     * Constructor
     *
     * @param bool $enabled Global on/off
     * @param LogFormat $format Output format
     * @param bool $useSyslog Use system logger instead of file/stderr
     * @param string|null $file Log file path (appended)
     * @param bool $includeRawInput Include raw input (PII risk)
     * @param bool $logSuccessEvents Log successful submissions
     * @param bool $logFailureEvents Log validation/mail failures
     * @param callable|null $formatter Custom formatter (level, message, context, iso8601): string
     */
    public function __construct(
        bool $enabled = true,
        LogFormat $format = LogFormat::Text,
        bool $useSyslog = false,
        ?string $file = null,
        bool $includeRawInput = false,
        bool $logSuccessEvents = true,
        bool $logFailureEvents = true,
        ?callable $formatter = null,
    ) {
        $this->enabled = $enabled;
        $this->format = $format;
        $this->useSyslog = $useSyslog;
        $this->file = $file;
        $this->includeRawInput = $includeRawInput;
        $this->logSuccessEvents = $logSuccessEvents;
        $this->logFailureEvents = $logFailureEvents;
        $this->formatter = $formatter !== null ? $formatter(...) : null;
    }
    
    // ------------------------------------------------------------
    // Fluent Configuration (no naming collisions with domain methods)
    // ------------------------------------------------------------
    
    public function enable(bool $state = true): self
    {
        $this->enabled = $state;
        return $this;
    }
    
    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }
    
    public function asJson(bool $state = true): self
    {
        $this->format = $state ? LogFormat::Json : LogFormat::Text;
        return $this;
    }
    
    public function asText(): self
    {
        $this->format = LogFormat::Text;
        return $this;
    }
    
    public function toSyslog(bool $state = true): self
    {
        $this->useSyslog = $state;
        return $this;
    }
    
    public function toFile(?string $path): self
    {
        $this->file = $path;
        return $this;
    }
    
    public function includeRawInput(bool $state = true): self
    {
        $this->includeRawInput = $state;
        return $this;
    }
    
    public function enableSuccessEvents(bool $state = true): self
    {
        $this->logSuccessEvents = $state;
        return $this;
    }
    
    public function enableFailureEvents(bool $state = true): self
    {
        $this->logFailureEvents = $state;
        return $this;
    }
    
    /**
     * @param callable(LogLevel,string,array<string,mixed>,string):string $formatter
     */
    public function withFormatter(callable $formatter): self
    {
        $this->formatter = $formatter(...);
        return $this;
    }
    
    // ------------------------------------------------------------
    // Generic Logging API
    // ------------------------------------------------------------
    
    /**
     * @param array<string,mixed> $context
     */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        $line = $this->formatLine($level, $message, $context);
        $this->dispatch($line);
    }
    
    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::Debug, $message, $context);
    }
    
    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::Info, $message, $context);
    }
    
    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::Error, $message, $context);
    }
    
    // ------------------------------------------------------------
    // Domain-Specific Helpers (no name overlap with config methods)
    // ------------------------------------------------------------
    
    /**
     * Log the beginning of a submission.
     *
     * @param array<string,mixed> $input
     */
    public function recordSubmissionStart(array $input): void
    {
        $payload = $this->includeRawInput
            ? ['input' => $input]
            : ['fields' => array_keys($input)];
        $this->info('Form submission received', $payload);
    }
    
    /**
     * Log validation errors (structured or interpolated).
     *
     * @param array<string, list<mixed>> $errors
     */
    public function recordValidationErrors(array $errors): void
    {
        if ($this->logFailureEvents) {
            $this->error('Validation failed', ['errors' => $errors]);
        }
    }
    
    /**
     * Log successful completion (mail sent).
     *
     * @param array<string,mixed> $data Normalized data
     * @param array<string,mixed> $meta e.g. ['to' => [...], 'subject' => '...']
     */
    public function recordSuccess(array $data, array $meta = []): void
    {
        if ($this->logSuccessEvents) {
            $this->info('Form submission succeeded', [
                'fields' => array_keys($data),
                ...($meta ?: []),
            ]);
        }
    }
    
    /**
     * Log an exception that occurred during processing.
     */
    public function recordException(Throwable $e): void
    {
        if ($this->logFailureEvents) {
            $this->error('Submission failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
    
    // ------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------
    
    /** @param array<string,mixed> $context */
    private function formatLine(LogLevel $level, string $message, array $context): string
    {
        $time = new DateTimeImmutable()->format(DATE_ATOM);
        
        if ($this->formatter !== null) {
            return ($this->formatter)($level, $message, $context, $time);
        }
        
        if ($this->format === LogFormat::Json) {
            return (string)json_encode([
                'time' => $time,
                'level' => $level->value,
                'message' => $message,
                'context' => $context,
            ], self::JSON_FLAGS);
        }
        
        $json = (string)json_encode($context, self::JSON_FLAGS);
        $ctx = $context === [] ? '' : ' ' . $json;
        return "[{$time}] [" . strtoupper($level->value) . "] {$message}{$ctx}";
    }
    
    private function dispatch(string $line): void
    {
        if ($this->useSyslog) {
            // LOG_INFO is enough for our lightweight needs; map by $level if you prefer.
            syslog(LOG_INFO, $line);
            return;
        }
        
        if ($this->file !== null) {
            // Atomic append with lock; suppress warnings if path is temporarily unwritable.
            @file_put_contents($this->file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            return;
        }
        
        // Fallback to PHP's error_log (stderr or configured SAPI log).
        error_log($line);
    }
}
