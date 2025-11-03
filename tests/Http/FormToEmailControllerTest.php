<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Http;

use FormToEmail\Enum\FieldRole;
use PHPUnit\Framework\TestCase;
use FormToEmail\Http\FormToEmailController;
use FormToEmail\Core\FormDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Validation\Rules\RequiredRule;
use FormToEmail\Validation\Rules\EmailRule;
use FormToEmail\Tests\Fake\FakeMailerAdapter;

/**
 * Integration test for FormToEmailController.
 *
 * Uses the test-safe handleRequest() method (no exit()).
 */
final class FormToEmailControllerTest extends TestCase
{
    public function testControllerReturnsOkAndSendsEmail(): void
    {
        // Prepare a minimal valid form
        $form = (new FormDefinition())
            ->add(new FieldDefinition(
                'email',
                roles: [FieldRole::SenderEmail],
                rules: [new RequiredRule(), new EmailRule()]
            ));
        
        // Fake mailer avoids real SMTP
        $mailer = new FakeMailerAdapter();
        
        // Instantiate controller with test recipients
        $controller = new FormToEmailController(
            form: $form,
            mailer: $mailer,
            recipients: ['contact@example.com']
        );
        
        // Simulate a POST request with valid JSON body
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: json_encode(['email' => 'moi@jturbide.com'])
        );
        
        $this->assertSame(['code' => 'ok'], $response);
        $this->assertCount(1, $mailer->sent);
        $this->assertSame('moi@jturbide.com', $mailer->sent[0]->replyToEmail);
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
        
        $this->assertSame(['code' => 'invalid_json'], $response);
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
        
        $this->assertSame(['code' => 'invalid_method'], $response);
    }
    
    public function testControllerReturnsValidationError(): void
    {
        $form = (new FormDefinition())
            ->add(new FieldDefinition('email', rules: [new RequiredRule(), new EmailRule()]));
        
        $mailer = new FakeMailerAdapter();
        $controller = new FormToEmailController($form, $mailer, ['contact@example.com']);
        
        $response = $controller->handleRequest(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: json_encode(['email' => 'not-an-email'])
        );
        
        $this->assertSame('validation_error', $response['code']);
        $this->assertArrayHasKey('email', $response['errors']);
    }
}
