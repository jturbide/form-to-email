<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationError;
use FormToEmail\Core\ErrorDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Core\FieldProcessor
 *
 * Contract and behavioral expectations for any FieldProcessor implementation.
 */
final class FieldProcessorTest extends TestCase
{
    private FormContext $context;
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->context = new FormContext(['email' => ' raw@example.com ']);
        $this->field = new FieldDefinition('email');
    }
    
    public function testImplementsExpectedMethodSignature(): void
    {
        $reflection = new \ReflectionClass(FieldProcessor::class);
        $this->assertTrue($reflection->hasMethod('process'));
        $method = $reflection->getMethod('process');
        
        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertSame('value', $params[0]->getName());
        $this->assertSame(FieldDefinition::class, $params[1]->getType()?->getName());
        $this->assertSame(FormContext::class, $params[2]->getType()?->getName());
        $this->assertSame('mixed', (string) $method->getReturnType());
    }
    
    public function testProcessorCanTransformValue(): void
    {
        $processor = new class implements FieldProcessor {
            public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
            {
                // Trim and lowercase for test
                return strtolower(trim((string) $value));
            }
        };
        
        $result = $processor->process('  Foo@Example.COM ', $this->field, $this->context);
        $this->assertSame('foo@example.com', $result);
    }
    
    public function testProcessorCanAddErrorsToContext(): void
    {
        $processor = new class implements FieldProcessor {
            public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
            {
                if (!is_string($value) || !str_contains($value, '@')) {
                    $context->addError($field->getName(), new ErrorDefinition('invalid_email', 'Must contain @'));
                }
                return $value;
            }
        };
        
        // invalid value
        $processor->process('not-an-email', $this->field, $this->context);
        
        $this->assertTrue($this->context->hasError('email'));
        $errors = $this->context->getFieldErrors('email');
        $this->assertCount(1, $errors);
        $this->assertInstanceOf(ValidationError::class, $errors[0]);
        $this->assertSame('invalid_email', $errors[0]->getCode());
    }
    
    public function testProcessorCanModifyFormContextValues(): void
    {
        $processor = new class implements FieldProcessor {
            public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
            {
                $new = strtoupper((string) $value);
                $context->setValue($field->getName(), $new);
                return $new;
            }
        };
        
        $result = $processor->process('foo', $this->field, $this->context);
        
        $this->assertSame('FOO', $result);
        $this->assertSame('FOO', $this->context->getValue('email'));
    }
    
    // ---------------------------------------------------------------------
    // Serialization behavior
    // ---------------------------------------------------------------------
    
    public function testAnonymousProcessorIsNotSerializable(): void
    {
        $processor = new class implements FieldProcessor {
            public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
            {
                return $value;
            }
        };
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('is not allowed');
        serialize($processor);
    }
}
