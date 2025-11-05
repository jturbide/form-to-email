<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\FormDefinition;
use FormToEmail\Core\ValidationError;
use FormToEmail\Core\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Core\FormDefinition
 * @covers \FormToEmail\Core\FormContext
 * @covers \FormToEmail\Core\ValidationResult
 *
 * Focuses on validating the integration of FormDefinition, FormContext,
 * and ValidationResult — not specific rule logic.
 */
final class ValidationTest extends TestCase
{
    public function testEmptyFormProducesValidResult(): void
    {
        $form = new FormDefinition();
        $result = $form->validate([]);
        
        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->valid);
        $this->assertSame([], $result->allErrors());
        $this->assertSame([], $result->allData());
    }
    
    public function testFormCollectsAndSerializesErrors(): void
    {
        // Simulate a processor injecting an error manually
        $form = new FormDefinition();
        $field = new FieldDefinition('username');
        
        // Anonymous inline processor emulating a failing rule
        $field->addProcessor(new class implements \FormToEmail\Core\FieldProcessor {
            public function process(mixed $value, \FormToEmail\Core\FieldDefinition $field, \FormToEmail\Core\FormContext $ctx): mixed
            {
                $ctx->addError(
                    $field->getName(),
                    new ErrorDefinition(
                        code: 'too_short',
                        message: 'Value must be at least {min} chars.',
                        context: ['min' => 3],
                        field: $field->getName()
                    )
                );
                return $value;
            }
        });
        
        $form->add($field);
        
        $result = $form->validate(['username' => 'xy']);
        
        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertFalse($result->valid);
        $this->assertTrue($result->failed());
        $this->assertTrue($result->hasError('username'));
        
        $errors = $result->getErrors('username');
        $this->assertCount(1, $errors);
        $this->assertContainsOnlyInstancesOf(ValidationError::class, $errors);
        
        $error = $errors[0];
        $this->assertSame('too_short', $error->getCode());
        $this->assertSame('username', $error->getField());
        $this->assertSame(['min' => 3], $error->getContext());
        
        // Check message interpolation
        $msg = $result->interpolate($error);
        $this->assertSame('Value must be at least 3 chars.', $msg);
        
        // Ensure serialization structure is stable
        $array = $result->toArray();
        $this->assertArrayHasKey('valid', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('data', $array);
        
        $serializedError = $array['errors']['username'][0];
        $this->assertSame('too_short', $serializedError['code']);
        $this->assertSame('Value must be at least {min} chars.', $serializedError['message']);
        $this->assertArrayHasKey('context', $serializedError);
        $this->assertArrayHasKey('field', $serializedError);
    }
    
    public function testFormContextStoresAndRetrievesValues(): void
    {
        $ctx = new FormContext(['email' => ' raw@example.com ']);
        $this->assertSame(' raw@example.com ', $ctx->getInput('email'));
        
        $ctx->setValue('email', 'clean@example.com');
        $this->assertSame('clean@example.com', $ctx->getValue('email'));
        $this->assertSame(['email' => 'clean@example.com'], $ctx->allData());
        
        $this->assertFalse($ctx->hasAnyErrors());
        $this->assertSame([], $ctx->allErrors());
    }
    
    public function testValidationResultCanBeCreatedFromContext(): void
    {
        $ctx = new FormContext(['name' => 'Julien']);
        $ctx->setValue('name', 'Julien');
        
        $ctx->addError(
            'name',
            new ErrorDefinition(
                code: 'test_error',
                message: 'This is a test for {field}.',
                context: ['field' => 'name'],
                field: 'name'
            )
        );
        
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertFalse($result->valid);
        $this->assertTrue($result->hasError('name'));
        
        $error = $result->firstError('name');
        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('test_error', $error->getCode());
        
        $msg = $result->interpolate($error);
        $this->assertSame('This is a test for name.', $msg);
        
        $array = $result->toArray(interpolated: true);
        $this->assertArrayHasKey('name', $array['errors']);
        $this->assertIsArray($array['errors']['name']);
        $this->assertStringContainsString('test', $array['errors']['name'][0]);
    }
}
