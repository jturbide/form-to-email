<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Fake;

use FormToEmail\Mail\MailPayload;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for FakeMailerAdapter.
 *
 * Ensures deterministic, analyzer-safe behavior for:
 *  - send() flow
 *  - failure simulation
 *  - delay and hook callbacks
 *  - helper assertions
 *  - fluent chaining
 */
final class FakeMailerAdapterTest extends TestCase
{
    private FakeMailerAdapter $fake;
    
    protected function setUp(): void
    {
        $this->fake = new FakeMailerAdapter();
    }
    
    private function makePayload(): MailPayload
    {
        return new MailPayload(
            to: ['demo@example.com'],
            subject: 'Hello World',
            htmlBody: '<b>Hello</b>',
            textBody: 'Hello',
            replyToEmail: 'sender@example.com',
            replyToName: 'Tester'
        );
    }
    
    // ---------------------------------------------------------------------
    // Basic send behavior
    // ---------------------------------------------------------------------
    
    public function testSendStoresPayloadInMemory(): void
    {
        $payload = $this->makePayload();
        $this->fake->send($payload);
        
        self::assertCount(1, $this->fake->sent);
        self::assertSame($payload, $this->fake->sent[0]);
        self::assertInstanceOf(MailPayload::class, $this->fake->last());
    }
    
    public function testResetClearsRecordedPayloads(): void
    {
        $this->fake->send($this->makePayload());
        $this->fake->reset();
        self::assertSame([], $this->fake->sent);
    }
    
    public function testCountReturnsNumberOfSentMails(): void
    {
        $this->fake->send($this->makePayload());
        $this->fake->send($this->makePayload());
        self::assertSame(2, $this->fake->count());
    }
    
    // ---------------------------------------------------------------------
    // Failure simulation
    // ---------------------------------------------------------------------
    
    public function testThrowOnSendTriggersRuntimeException(): void
    {
        $this->fake->throwOnSend = true;
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Simulated mail failure');
        
        $this->fake->send($this->makePayload());
    }
    
    public function testFailNextFluentMethodThrowsWithCustomMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Custom failure');
        
        $this->fake->failNext('Custom failure')->send($this->makePayload());
    }
    
    // ---------------------------------------------------------------------
    // Delay simulation
    // ---------------------------------------------------------------------
    
    public function testWithDelayAppliesApproximateWait(): void
    {
        $this->fake->withDelay(20); // 20 ms
        $start = hrtime(true);
        $this->fake->send($this->makePayload());
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        
        self::assertGreaterThanOrEqual(15, $elapsedMs);
    }
    
    // ---------------------------------------------------------------------
    // Hook callback
    // ---------------------------------------------------------------------
    
    public function testWithHookExecutesCallbackBeforeRecording(): void
    {
        $called = false;
        $capturedSubject = null;
        
        $this->fake->withHook(function (MailPayload $payload) use (&$called, &$capturedSubject): void {
            $called = true;
            $capturedSubject = $payload->subject;
        });
        
        $payload = $this->makePayload();
        $this->fake->send($payload);
        
        self::assertTrue($called);
        self::assertSame('Hello World', $capturedSubject);
        self::assertCount(1, $this->fake->sent);
    }
    
    // ---------------------------------------------------------------------
    // Assertion helpers
    // ---------------------------------------------------------------------
    
    public function testAssertSentThrowsIfNone(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected at least one email to be sent');
        $this->fake->assertSent();
    }
    
    public function testAssertNothingSentThrowsIfSomeWereSent(): void
    {
        $this->fake->send($this->makePayload());
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected no emails to be sent');
        $this->fake->assertNothingSent();
    }
    
    public function testAssertSentPassesWhenMailWasSent(): void
    {
        $this->fake->send($this->makePayload());
        $this->fake->assertSent();
        self::assertTrue(true);
    }
    
    public function testAssertNothingSentPassesWhenNone(): void
    {
        $this->fake->assertNothingSent();
        self::assertTrue(true);
    }
    
    // ---------------------------------------------------------------------
    // Fluent configuration
    // ---------------------------------------------------------------------
    
    public function testFluentMethodsReturnSelfForChaining(): void
    {
        $result = $this->fake
            ->withDelay(5)
            ->withHook(fn() => null)
            ->failNext('Fluent failure');
        
        self::assertSame($this->fake, $result);
        self::assertTrue($this->fake->throwOnSend);
        self::assertSame('Fluent failure', $this->fake->failureMessage);
        self::assertIsCallable($this->fake->onSend);
    }
    
    public function testFluentChainInvokesAllFeatures(): void
    {
        $called = false;
        
        $fake = (new FakeMailerAdapter())
            ->withHook(function (MailPayload $payload) use (&$called): void {
                $called = true;
            })
            ->withDelay(1);
        
        $fake->send($this->makePayload());
        
        self::assertTrue($called);
        self::assertCount(1, $fake->sent);
        self::assertInstanceOf(MailPayload::class, $fake->last());
    }
}
