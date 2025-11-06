<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\Logger;
use FormToEmail\Enum\{LogLevel, LogFormat};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Class: LoggerTest
 *
 * Full coverage suite for FormToEmail\Core\Logger.
 * Targets correctness, resilience, and API consistency.
 */
final class LoggerTest extends TestCase
{
    private string $tempFile;
    
    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'logger_');
        self::assertIsString($file, 'Unable to create temp log file');
        $this->tempFile = $file;
    }
    
    protected function tearDown(): void
    {
        @unlink($this->tempFile);
    }
    
    // ---------------------------------------------------------------------
    // Providers
    // ---------------------------------------------------------------------
    
    /**
     * @return array<array{LogLevel, LogFormat}>
     */
    public static function levelFormatProvider(): array
    {
        return [
            [LogLevel::Info, LogFormat::Text],
            [LogLevel::Error, LogFormat::Json],
            [LogLevel::Debug, LogFormat::Text],
        ];
    }
    
    /**
     * @return array<array{callable(Logger):void,string}>
     */
    public static function helperProvider(): array
    {
        return [
            [fn(Logger $l) => $l->recordSubmissionStart(['a' => 1]), 'submission'],
            [fn(Logger $l) => $l->recordValidationErrors(['field' => ['required']]), 'validation'],
            [fn(Logger $l) => $l->recordSuccess(['ok' => true], ['to' => ['a@b.c']]), 'succeeded'],
            [fn(Logger $l) => $l->recordException(new RuntimeException('Boom')), 'failed'],
        ];
    }
    
    // ---------------------------------------------------------------------
    // Core Logging
    // ---------------------------------------------------------------------
    
    #[DataProvider('levelFormatProvider')]
    public function testLoggerWritesExpectedEntry(LogLevel $level, LogFormat $format): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true, format: $format);
        $logger->log($level, 'Testing log', ['key' => 'value']);
        
        $content = strtolower(trim((string) file_get_contents($this->tempFile)));
        
        self::assertNotSame('', $content);
        self::assertStringContainsString($level->value, $content);
        self::assertStringContainsString('testing log', $content);
        self::assertStringContainsString('value', $content);
    }
    
    // ---------------------------------------------------------------------
    // Domain Helpers
    // ---------------------------------------------------------------------
    
    #[DataProvider('helperProvider')]
    public function testDomainHelpersProduceOutput(callable $invoker, string $expected): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true);
        $invoker($logger);
        
        $contents = strtolower(trim((string) file_get_contents($this->tempFile)));
        self::assertStringContainsString($expected, $contents);
    }
    
    // ---------------------------------------------------------------------
    // Customization & Behavior
    // ---------------------------------------------------------------------
    
    public function testCustomFormatterOverridesDefault(): void
    {
        $formatter = fn(LogLevel $lvl, string $msg, array $ctx, string $t) =>
            "{$t}|{$lvl->value}|{$msg}|id=" . ($ctx['id'] ?? '?');
        
        $logger = new Logger(file: $this->tempFile, enabled: true, formatter: $formatter);
        $logger->error('Custom', ['id' => 99]);
        
        $contents = trim((string) file_get_contents($this->tempFile));
        self::assertStringContainsString('|error|custom|id=99', strtolower($contents));
    }
    
    public function testDisablePreventsAnyWrites(): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: false);
        $logger->info('Nope');
        self::assertSame('', trim((string) file_get_contents($this->tempFile)));
    }
    
    public function testEnableThenDisableFlow(): void
    {
        $logger = new Logger(file: $this->tempFile);
        $logger->enable();
        $logger->info('Active');
        $logger->disable();
        $logger->info('Inactive');
        
        $lines = file($this->tempFile, FILE_IGNORE_NEW_LINES);
        self::assertCount(1, $lines);
        self::assertStringContainsString('active', strtolower($lines[0]));
    }
    
    public function testIncludeRawInputAddsFieldData(): void
    {
        $logger = (new Logger(file: $this->tempFile, enabled: true))
            ->includeRawInput();
        
        $logger->recordSubmissionStart(['field' => 'x']);
        $content = trim((string) file_get_contents($this->tempFile));
        
        self::assertStringContainsString('"field":"x"', $content);
    }
    
    public function testJsonFormatIsValidJson(): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true, format: LogFormat::Json);
        $logger->info('Hello', ['foo' => 'bar']);
        $json = trim((string) file_get_contents($this->tempFile));
        self::assertJson($json);
        $decoded = json_decode($json, true);
        self::assertSame('hello', strtolower($decoded['message']));
        self::assertSame('bar', $decoded['context']['foo']);
    }
    
    public function testTextFormatContainsReadableString(): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true, format: LogFormat::Text);
        $logger->error('Failure', ['foo' => 'bar']);
        $content = trim((string) file_get_contents($this->tempFile));
        self::assertStringContainsString('[', $content);
        self::assertStringContainsString('error', strtolower($content));
        self::assertStringContainsString('foo', strtolower($content));
    }
    
    public function testMultipleEntriesAreAppended(): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true);
        $logger->info('One');
        $logger->info('Two');
        $lines = file($this->tempFile, FILE_IGNORE_NEW_LINES);
        self::assertCount(2, $lines);
        self::assertStringContainsString('one', strtolower($lines[0]));
        self::assertStringContainsString('two', strtolower($lines[1]));
    }
    
    public function testInvalidUtf8DoesNotCrash(): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true, format: LogFormat::Json);
        $logger->info('Bad UTF8', ['data' => "\xC3\x28"]);
        $json = trim((string) file_get_contents($this->tempFile));
        self::assertJson($json);
        $decoded = json_decode($json, true);
        self::assertSame('bad utf8', strtolower($decoded['message']));
    }
    
    public function testUnwritableFileIsHandledGracefully(): void
    {
        $logger = new Logger(file: '/root/forbidden-' . uniqid() . '.log', enabled: true);
        $logger->info('Should not throw');
        self::assertTrue(true);
    }
    
    public function testFluentChainAppliesConfiguration(): void
    {
        $logger = (new Logger())
            ->enable()
            ->asJson()
            ->includeRawInput()
            ->enableSuccessEvents(false)
            ->enableFailureEvents(true)
            ->toFile($this->tempFile);
        
        $logger->debug('Fluent chain works');
        
        $content = strtolower(trim((string) file_get_contents($this->tempFile)));
        self::assertStringContainsString('fluent chain works', $content);
    }
    
    public function testSyslogFallback(): void
    {
        // Syslog write cannot be easily intercepted, but we test the code path.
        $logger = (new Logger(enabled: true))
            ->toSyslog(true);
        
        // Should not throw even without syslog daemon.
        $logger->info('Testing syslog');
        self::assertTrue(true);
    }
    
    // ---------------------------------------------------------------------
    // Realistic Lifecycle Simulation
    // ---------------------------------------------------------------------
    
    public function testSimulatedSuccessfulSubmission(): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true);
        $logger->recordSubmissionStart(['name' => 'Julien']);
        $logger->recordSuccess(['name' => 'Julien'], ['to' => ['demo@example.com']]);
        
        $contents = strtolower(trim((string) file_get_contents($this->tempFile)));
        self::assertStringContainsString('submission received', $contents);
        self::assertStringContainsString('succeeded', $contents);
        self::assertStringContainsString('demo@example.com', $contents);
    }
    
    public function testSimulatedValidationFailure(): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true);
        $logger->recordSubmissionStart(['email' => '']);
        $logger->recordValidationErrors(['email' => ['required']]);
        $contents = strtolower(trim((string) file_get_contents($this->tempFile)));
        self::assertStringContainsString('validation failed', $contents);
    }
    
    public function testSimulatedExceptionFailure(): void
    {
        $logger = new Logger(file: $this->tempFile, enabled: true);
        try {
            throw new RuntimeException('Mailer connection lost');
        } catch (Throwable $e) {
            $logger->recordException($e);
        }
        
        $contents = strtolower(trim((string) file_get_contents($this->tempFile)));
        self::assertStringContainsString('submission failed', $contents);
        self::assertStringContainsString('mailer connection lost', $contents);
    }
}
