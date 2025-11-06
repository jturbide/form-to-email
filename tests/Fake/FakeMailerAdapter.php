<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Fake;

use FormToEmail\Mail\MailerAdapter;
use FormToEmail\Mail\MailPayload;
use RuntimeException;

/**
 * Fake mailer for testing without real SMTP I/O.
 *
 * Provides:
 *  - In-memory storage of sent payloads
 *  - Optional failure simulation
 *  - Optional artificial delay (for timing tests)
 *  - Optional send hook callback (for assertions or mutation)
 *
 * Fully analyzer-safe and deterministic for PHPUnit.
 */
final class FakeMailerAdapter implements MailerAdapter
{
    /**
     * All successfully sent payloads.
     *
     * @var list<MailPayload>
     */
    public array $sent = [];
    
    /**
     * When true, send() throws a RuntimeException instead of sending.
     */
    public bool $throwOnSend = false;
    
    /**
     * Optional message used when throwOnSend = true.
     */
    public string $failureMessage = 'Simulated mail failure';
    
    /**
     * Optional delay in milliseconds before completing send().
     * Used for concurrency or timeout simulation.
     */
    public int $delayMs = 0;
    
    /**
     * Optional hook executed each time send() is invoked.
     * Receives the MailPayload about to be sent.
     *
     * @var null|callable(MailPayload):void
     */
    public $onSend = null;
    
    // ---------------------------------------------------------------------
    // MailerAdapter Implementation
    // ---------------------------------------------------------------------
    
    #[\Override]
    public function send(MailPayload $payload): void
    {
        // 1. Simulate delay if requested
        if ($this->delayMs > 0) {
            usleep($this->delayMs * 1000);
        }
        
        // 2. Invoke optional user-provided hook
        if (is_callable($this->onSend)) {
            ($this->onSend)($payload);
        }
        
        // 3. Failure simulation
        if ($this->throwOnSend) {
            throw new RuntimeException($this->failureMessage);
        }
        
        // 4. Normal path: record payload in memory
        $this->sent[] = $payload;
    }
    
    // ---------------------------------------------------------------------
    // Helpers for Tests
    // ---------------------------------------------------------------------
    
    /** Clear all recorded payloads. */
    public function reset(): void
    {
        $this->sent = [];
    }
    
    /** Return number of sent messages. */
    public function count(): int
    {
        return count($this->sent);
    }
    
    /**
     * Return the last sent payload (if any).
     */
    public function last(): ?MailPayload
    {
        return $this->sent === [] ? null : end($this->sent);
    }
    
    /**
     * Assert that at least one mail was sent (used directly in tests).
     */
    public function assertSent(): void
    {
        if ($this->sent === []) {
            throw new RuntimeException('Expected at least one email to be sent, none recorded.');
        }
    }
    
    /**
     * Assert that no mails were sent (useful for validation-error scenarios).
     */
    public function assertNothingSent(): void
    {
        if ($this->sent !== []) {
            throw new RuntimeException('Expected no emails to be sent, but some were recorded.');
        }
    }
    
    /**
     * Configure this fake to simulate a failure on the next send.
     *
     * @param string $message Optional custom failure text
     */
    public function failNext(string $message = 'Simulated mail failure'): self
    {
        $this->throwOnSend = true;
        $this->failureMessage = $message;
        return $this;
    }
    
    /**
     * Configure this fake to introduce a small artificial delay.
     */
    public function withDelay(int $milliseconds): self
    {
        $this->delayMs = $milliseconds;
        return $this;
    }
    
    /**
     * Configure a custom send hook.
     */
    public function withHook(callable $callback): self
    {
        $this->onSend = $callback;
        return $this;
    }
}
