<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Integration;

use FormToEmail\Core\Logger;
use FormToEmail\Enum\{LogLevel, LogFormat};
use FormToEmail\Mail\MailPayload;
use FormToEmail\Tests\Fake\FakeMailerAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Integration test verifying Logger and FakeMailerAdapter
 * produce consistent results during form lifecycle simulation.
 */
final class LoggerMailerIntegrationTest extends TestCase
{
    private string $logFile;
    
    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'logger_mailer_');
        self::assertIsString($file);
        $this->logFile = $file;
    }
    
    protected function tearDown(): void
    {
        @unlink($this->logFile);
    }
    
    private function makePayload(string $email = 'user@example.com'): MailPayload
    {
        return new MailPayload(
            to: ['contact@example.com'],
            subject: 'Test',
            htmlBody: '<b>hi</b>',
            textBody: 'hi',
            replyToEmail: $email,
            replyToName: 'Unit Tester'
        );
    }
    
    // ---------------------------------------------------------------------
    // Success Flow
    // ---------------------------------------------------------------------
    
    public function testSuccessfulSendIsLogged(): void
    {
        $logger = new Logger(enabled: true, file: $this->logFile, format: LogFormat::Json);
        $mailer = new FakeMailerAdapter();
        
        // Simulate normal send + log
        $payload = $this->makePayload();
        $logger->recordSubmissionStart(['email' => 'user@example.com']);
        $mailer->send($payload);
        $logger->recordSuccess(['email' => 'user@example.com'], ['to' => $payload->to]);
        
        $contents = trim((string) file_get_contents($this->logFile));
        self::assertNotSame('', $contents);
        self::assertStringContainsString('"form submission received"', strtolower($contents));
        self::assertStringContainsString('"form submission succeeded"', strtolower($contents));
        self::assertStringContainsString('"email"', $contents);
        self::assertSame(1, $mailer->count());
    }
    
    // ---------------------------------------------------------------------
    // Validation Failure Flow
    // ---------------------------------------------------------------------
    
    public function testValidationFailureLogsErrorAndNoMailSent(): void
    {
        $logger = new Logger(enabled: true, file: $this->logFile, format: LogFormat::Text);
        $mailer = new FakeMailerAdapter();
        
        // Simulate validation failure before send
        $logger->recordSubmissionStart(['email' => 'bad']);
        $logger->recordValidationErrors(['email' => ['invalid format']]);
        
        $mailer->assertNothingSent();
        
        $contents = strtolower(trim((string) file_get_contents($this->logFile)));
        self::assertStringContainsString('validation failed', $contents);
        self::assertStringContainsString('email', $contents);
    }
    
    // ---------------------------------------------------------------------
    // Mail Failure Flow
    // ---------------------------------------------------------------------
    
    public function testMailerFailureIsCaughtAndLogged(): void
    {
        $logger = new Logger(enabled: true, file: $this->logFile, format: LogFormat::Text);
        $mailer = (new FakeMailerAdapter())->failNext('SMTP unreachable');
        
        $payload = $this->makePayload();
        
        // Simulate real-world try/catch around send
        $logger->recordSubmissionStart(['email' => $payload->replyToEmail]);
        
        try {
            $mailer->send($payload);
            $logger->recordSuccess(['email' => $payload->replyToEmail]);
        } catch (RuntimeException $e) {
            $logger->recordException($e);
        }
        
        $contents = strtolower(trim((string) file_get_contents($this->logFile)));
        
        self::assertStringContainsString('submission failed', $contents);
        self::assertStringContainsString('smtp unreachable', $contents);
        self::assertStringContainsString('runtimeexception', $contents);
        $mailer->assertNothingSent();
    }
    
    // ---------------------------------------------------------------------
    // Custom Formatter + Syslog Paths
    // ---------------------------------------------------------------------
    
    public function testCustomFormatterIntegratesWithMailer(): void
    {
        $formatter = fn(LogLevel $lvl, string $msg, array $ctx, string $t) =>
        "{$t}|{$lvl->value}|{$msg}";
        
        $logger = new Logger(enabled: true, file: $this->logFile, formatter: $formatter);
        $mailer = new FakeMailerAdapter();
        
        $payload = $this->makePayload('john@example.com');
        
        $logger->recordSubmissionStart(['email' => 'john@example.com']);
        $mailer->send($payload);
        $logger->recordSuccess(['email' => 'john@example.com'], ['to' => $payload->to]);
        
        $contents = trim((string) file_get_contents($this->logFile));
        self::assertStringContainsString('|info|form submission received', strtolower($contents));
        self::assertStringContainsString('|info|form submission succeeded', strtolower($contents));
    }
    
    public function testSyslogDoesNotThrow(): void
    {
        // This test ensures syslog() branch executes without error.
        $logger = (new Logger(enabled: true))->toSyslog(true);
        $mailer = new FakeMailerAdapter();
        
        $payload = $this->makePayload();
        $logger->recordSubmissionStart(['email' => 'syslog@site.com']);
        $mailer->send($payload);
        $logger->recordSuccess(['email' => 'syslog@site.com']);
        
        self::assertTrue(true); // If we reach here, syslog() didn’t throw.
    }
    
    // ---------------------------------------------------------------------
    // Mixed Conditions
    // ---------------------------------------------------------------------
    
    public function testMultipleSubmissionsAppendToSameFile(): void
    {
        $logger = new Logger(enabled: true, file: $this->logFile, format: LogFormat::Text);
        $mailer = new FakeMailerAdapter();
        
        for ($i = 1; $i <= 3; $i++) {
            $email = "user{$i}@example.com";
            $logger->recordSubmissionStart(['email' => $email]);
            $mailer->send($this->makePayload($email));
            $logger->recordSuccess(['email' => $email]);
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES);
        self::assertGreaterThanOrEqual(3, count($lines));
        self::assertStringContainsString('"email"', $lines[0]);
        self::assertStringContainsString('"email"', end($lines));
    }
}
