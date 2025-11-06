<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\ValidationError;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Core\ValidationError
 * @covers \FormToEmail\Core\ErrorDefinition
 *
 * Contract test for ValidationError implementations.
 *
 * Ensures:
 *  - Interface methods exist and have correct types
 *  - Concrete implementation (ErrorDefinition) respects immutability
 *  - Serialization and string conversion behave as expected
 */
final class ValidationErrorTest extends TestCase
{
    // ---------------------------------------------------------------------
    // Contract validation
    // ---------------------------------------------------------------------
    
    public function testInterfaceDefinesExpectedMethods(): void
    {
        $reflection = new \ReflectionClass(ValidationError::class);
        $methods = array_map(fn(\ReflectionMethod $m) => $m->getName(), $reflection->getMethods());
        
        self::assertSame(
            ['getCode', 'getMessage', 'getContext', 'getField'],
            $methods,
            'ValidationError must define the expected method set'
        );
        
        $method = $reflection->getMethod('getContext');
        self::assertTrue($method->hasReturnType());
        self::assertSame('array', $method->getReturnType()->getName());
    }
    
    // ---------------------------------------------------------------------
    // Implementation checks
    // ---------------------------------------------------------------------
    
    public function testErrorDefinitionImplementsValidationError(): void
    {
        $err = new ErrorDefinition(
            code: 'too_short',
            message: 'Minimum {min} characters required.',
            context: ['min' => 3, 'actual' => 1],
            field: 'password'
        );
        
        self::assertInstanceOf(ValidationError::class, $err);
        self::assertSame('too_short', $err->getCode());
        self::assertSame('Minimum {min} characters required.', $err->getMessage());
        self::assertSame(['min' => 3, 'actual' => 1], $err->getContext());
        self::assertSame('password', $err->getField());
    }
    
    public function testErrorDefinitionIsImmutable(): void
    {
        $err = new ErrorDefinition('required', 'Field is required');
        $clone = $err->withMessage('translated');
        
        // Original unchanged
        self::assertSame('Field is required', $err->getMessage());
        // New instance with modified message
        self::assertSame('translated', $clone->getMessage());
        self::assertNotSame($err, $clone);
    }
    
    public function testWithContextMergesValues(): void
    {
        $err = new ErrorDefinition('range', 'Between {min}-{max}', ['min' => 1]);
        $updated = $err->withContext(['max' => 5]);
        
        self::assertSame(['min' => 1], $err->getContext());
        self::assertSame(['min' => 1, 'max' => 5], $updated->getContext());
    }
    
    public function testForFieldBindsFieldName(): void
    {
        $err = new ErrorDefinition('generic', 'Some error');
        $bound = $err->forField('email');
        
        self::assertNull($err->getField());
        self::assertSame('email', $bound->getField());
        self::assertNotSame($err, $bound);
    }
    
    // ---------------------------------------------------------------------
    // Serialization & string representation
    // ---------------------------------------------------------------------
    
    public function testToArrayContainsAllKeys(): void
    {
        $err = new ErrorDefinition('invalid', 'Wrong value', ['expected' => 'X'], 'field');
        $arr = $err->toArray();
        
        self::assertSame([
            'code' => 'invalid',
            'message' => 'Wrong value',
            'context' => ['expected' => 'X'],
            'field' => 'field',
        ], $arr);
    }
    
    public function testInterpolateReplacesPlaceholders(): void
    {
        $err = new ErrorDefinition('too_short', 'Min {min}, got {actual}', ['min' => 3, 'actual' => 1]);
        self::assertSame('Min 3, got 1', $err->interpolate());
    }
    
    public function testToStringReturnsInterpolatedMessage(): void
    {
        $err = new ErrorDefinition('x', 'Hi {name}', ['name' => 'Julien']);
        self::assertSame('Hi Julien', (string) $err);
    }
    
    public function testWithMessageAndWithContextAndForFieldChainable(): void
    {
        $err = (new ErrorDefinition('test'))
            ->withMessage('msg')
            ->withContext(['foo' => 'bar'])
            ->forField('email');
        
        self::assertSame('msg', $err->getMessage());
        self::assertSame(['foo' => 'bar'], $err->getContext());
        self::assertSame('email', $err->getField());
    }
    
    public function testErrorDefinitionIsSerializable(): void
    {
        $err = new ErrorDefinition('invalid', 'bad', ['x' => 1], 'field');
        $serialized = serialize($err);
        $restored = unserialize($serialized);
        
        self::assertInstanceOf(ErrorDefinition::class, $restored);
        self::assertSame($err->getCode(), $restored->getCode());
        self::assertSame($err->getMessage(), $restored->getMessage());
        self::assertSame($err->getContext(), $restored->getContext());
        self::assertSame($err->getField(), $restored->getField());
    }
}
