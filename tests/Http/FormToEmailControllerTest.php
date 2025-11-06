<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Http;

use FormToEmail\Core\{
    FieldDefinition,
    FormDefinition,
    Logger
};
use FormToEmail\Enum\{
    FieldRole,
    LogFormat
};
use FormToEmail\Http\FormToEmailController;
use FormToEmail\Mail\MailPayload;
use FormToEmail\Rule\{
    EmailRule,
    RequiredRule
};
use FormToEmail\Tests\Fake\FakeMailerAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Integration tests for FormToEmailController.
 *
 * Uses handleRequest() to avoid exit() and validate logic deterministically.
 */
final class FormToEmailControllerTest extends TestCase
{
    private function makeForm(): FormDefinition
    {
        return (new FormDefinition())
            ->add(new FieldDefinition(
                'email',
                roles: [FieldRole::SenderEmail],
                processors: [new RequiredRule(), new EmailRule()]
            ))
            ->add(new FieldDefinition(
                'name',
                roles: [FieldRole::SenderName],
                processors: [new RequiredRule()]
            ));
    }
    
    // ---------------------------------------------------------------------
    // Core Scenarios
    // ---------------------------------------------------------------------
    
    public function testControllerReturnsOkAndSendsEmail(): void
    {
        $form = $this->makeForm();
        $mailer = new FakeMailerAdapter();
        
        $controller = new FormToEmailController(
            form: $form,
            mailer: $mailer,
            recipients: ['contact@example.com']
        );
        
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: json_encode(['email' => 'moi@jturbide.com', 'name' => 'Julien'])
        );
        
        self::assertSame(['code' => 'ok'], $response);
        self::assertCount(1, $mailer->sent);
        self::assertInstanceOf(MailPayload::class, $mailer->sent[0]);
        self::assertSame('moi@jturbide.com', $mailer->sent[0]->replyToEmail);
    }
    
    public function testControllerReturnsInvalidJson(): void
    {
        $form = new FormDefinition();
        $mailer = new FakeMailerAdapter();
        
        $controller = new FormToEmailController($form, $mailer, ['contact@example.com']);
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: 'not-json'
        );
        
        self::assertSame(['code' => 'invalid_json'], $response);
    }
    
    public function testControllerReturnsInvalidMethod(): void
    {
        $form = new FormDefinition();
        $mailer = new FakeMailerAdapter();
        
        $controller = new FormToEmailController($form, $mailer, ['contact@example.com']);
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'GET'],
            rawBody: json_encode([])
        );
        
        self::assertSame(['code' => 'invalid_method'], $response);
    }
    
    public function testControllerReturnsValidationError(): void
    {
        $form = (new FormDefinition())
            ->add(new FieldDefinition('email', processors: [new RequiredRule(), new EmailRule()]));
        
        $mailer = new FakeMailerAdapter();
        $controller = new FormToEmailController($form, $mailer, ['contact@example.com']);
        
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: json_encode(['email' => 'not-an-email'])
        );
        
        self::assertSame('validation_error', $response['code']);
        self::assertArrayHasKey('email', $response['errors']);
    }
    
    // ---------------------------------------------------------------------
    // Additional / Edge Scenarios
    // ---------------------------------------------------------------------
    
    public function testControllerHandlesMailFailureGracefully(): void
    {
        $form = $this->makeForm();
        
        $mailer = new FakeMailerAdapter();
        $mailer->throwOnSend = true;
        
        $controller = new FormToEmailController($form, $mailer, ['contact@example.com']);
        
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: json_encode(['email' => 'test@domain.com', 'name' => 'X'])
        );
        
        self::assertSame(['code' => 'mail_failure'], $response);
    }
    
    public function testControllerHandlesOptionsPreflight(): void
    {
        $form = new FormDefinition();
        $mailer = new FakeMailerAdapter();
        $controller = new FormToEmailController($form, $mailer, ['contact@example.com']);
        
        $response = $controller->handleRequest(server: ['REQUEST_METHOD' => 'OPTIONS']);
        self::assertSame(['code' => 'ok'], $response);
    }
    
    public function testControllerReturnsOkWithLoggerEnabled(): void
    {
        $form = $this->makeForm();
        $mailer = new FakeMailerAdapter();
        
        // In-memory logger writes to temp file to simulate real logging
        $logFile = tempnam(sys_get_temp_dir(), 'formlog_');
        $logger = new Logger(
            enabled: true,
            file: $logFile,
            format: LogFormat::Json
        );
        
        $controller = new FormToEmailController(
            form: $form,
            mailer: $mailer,
            recipients: ['contact@example.com'],
            logger: $logger
        );
        
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: json_encode(['email' => 'success@site.com', 'name' => 'Valid'])
        );
        
        self::assertSame(['code' => 'ok'], $response);
        $contents = trim((string) file_get_contents($logFile));
        
        // At least one JSON log line
        self::assertStringContainsString('"message"', $contents);
        self::assertStringContainsString('form submission', strtolower($contents));
        
        @unlink($logFile);
    }
    
    public function testControllerLogsValidationFailure(): void
    {
        $form = (new FormDefinition())
            ->add(new FieldDefinition('email', processors: [new RequiredRule(), new EmailRule()]));
        
        $mailer = new FakeMailerAdapter();
        $logFile = tempnam(sys_get_temp_dir(), 'formlog_');
        $logger = new Logger(enabled: true, file: $logFile, format: LogFormat::Text);
        
        $controller = new FormToEmailController($form, $mailer, ['contact@example.com'], logger: $logger);
        
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: json_encode(['email' => 'invalid'])
        );
        
        self::assertSame('validation_error', $response['code']);
        $contents = trim((string) file_get_contents($logFile));
        self::assertStringContainsString('validation failed', strtolower($contents));
        
        @unlink($logFile);
    }
    
    public function testControllerLogsMailFailureException(): void
    {
        $form = $this->makeForm();
        
        $mailer = new FakeMailerAdapter();
        $mailer->throwOnSend = true;
        
        $logFile = tempnam(sys_get_temp_dir(), 'formlog_');
        $logger = new Logger(enabled: true, file: $logFile, format: LogFormat::Text);
        
        $controller = new FormToEmailController(
            form: $form,
            mailer: $mailer,
            recipients: ['contact@example.com'],
            logger: $logger
        );
        
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: json_encode(['email' => 'demo@domain.com', 'name' => 'X'])
        );
        
        self::assertSame(['code' => 'mail_failure'], $response);
        
        $contents = strtolower(trim((string) file_get_contents($logFile)));
        self::assertStringContainsString('submission failed', $contents);
        self::assertStringContainsString('runtimeexception', $contents);
        
        @unlink($logFile);
    }
    
    public function testControllerCanHandleEmptyBody(): void
    {
        $form = new FormDefinition();
        $mailer = new FakeMailerAdapter();
        $controller = new FormToEmailController($form, $mailer, ['contact@example.com']);
        
        $response = $controller->handleRequest(server: ['REQUEST_METHOD' => 'POST'], rawBody: '');
        self::assertSame(['code' => 'invalid_json'], $response);
    }
}
