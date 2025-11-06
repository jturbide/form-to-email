<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\ValidationError;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ErrorDefinition.
 *
 * Validates:
 *  - immutability & constructor initialization
 *  - getters
 *  - interpolation behavior
 *  - array conversion
 *  - string casting
 *  - "with*" helper methods
 */
final class ErrorDefinitionTest extends TestCase
{
    private function makeError(): ErrorDefinition
    {
        return new ErrorDefinition(
            code: 'too_short',
            message: 'Minimum {min} chars, got {actual}',
            context: ['min' => 3, 'actual' => 1],
            field: 'username'
        );
    }
    
    // ---------------------------------------------------------------------
    // Basic construction and interface
    // ---------------------------------------------------------------------
    
    public function testImplementsValidationErrorInterface(): void
    {
        $error = $this->makeError();
        self::assertInstanceOf(ValidationError::class, $error);
    }
    
    public function testConstructorSetsAllProperties(): void
    {
        $error = $this->makeError();
        
        self::assertSame('too_short', $error->getCode());
        self::assertSame('Minimum {min} chars, got {actual}', $error->getMessage());
        self::assertSame(['min' => 3, 'actual' => 1], $error->getContext());
        self::assertSame('username', $error->getField());
    }
    
    public function testDefaultsWhenOptionalParametersOmitted(): void
    {
        $error = new ErrorDefinition('required');
        
        self::assertSame('required', $error->getCode());
        self::assertSame('', $error->getMessage());
        self::assertSame([], $error->getContext());
        self::assertNull($error->getField());
    }
    
    // ---------------------------------------------------------------------
    // Interpolation & string casting
    // ---------------------------------------------------------------------
    
    public function testInterpolateReplacesPlaceholdersWithValues(): void
    {
        $error = $this->makeError();
        self::assertSame('Minimum 3 chars, got 1', $error->interpolate());
    }
    
    public function testInterpolateIgnoresMissingPlaceholders(): void
    {
        $error = new ErrorDefinition('invalid', 'Unexpected {foo}', []);
        self::assertSame('Unexpected {foo}', $error->interpolate());
    }
    
    public function testStringCastReturnsInterpolatedMessage(): void
    {
        $error = $this->makeError();
        self::assertSame($error->interpolate(), (string) $error);
    }
    
    // ---------------------------------------------------------------------
    // Array conversion
    // ---------------------------------------------------------------------
    
    public function testToArrayContainsAllFields(): void
    {
        $error = $this->makeError();
        $array = $error->toArray();
        
        self::assertSame('too_short', $array['code']);
        self::assertSame('Minimum {min} chars, got {actual}', $array['message']);
        self::assertSame(['min' => 3, 'actual' => 1], $array['context']);
        self::assertSame('username', $array['field']);
    }
    
    // ---------------------------------------------------------------------
    // Immutability and cloning behavior
    // ---------------------------------------------------------------------
    
    public function testWithMessageReturnsNewInstance(): void
    {
        $original = $this->makeError();
        $updated = $original->withMessage('New message');
        
        self::assertNotSame($original, $updated);
        self::assertSame('too_short', $updated->getCode());
        self::assertSame('New message', $updated->getMessage());
        self::assertSame($original->getContext(), $updated->getContext());
        self::assertSame($original->getField(), $updated->getField());
    }
    
    public function testWithContextMergesContext(): void
    {
        $original = $this->makeError();
        $updated = $original->withContext(['extra' => 42]);
        
        self::assertNotSame($original, $updated);
        self::assertArrayHasKey('extra', $updated->getContext());
        self::assertSame(42, $updated->getContext()['extra']);
        // Original context unchanged
        self::assertArrayNotHasKey('extra', $original->getContext());
    }
    
    public function testForFieldReturnsNewInstanceBoundToField(): void
    {
        $original = $this->makeError();
        $updated = $original->forField('email');
        
        self::assertNotSame($original, $updated);
        self::assertSame('email', $updated->getField());
        self::assertSame($original->getCode(), $updated->getCode());
    }
    
    public function testChainedWithersPreserveAllData(): void
    {
        $error = (new ErrorDefinition('invalid_email'))
            ->withMessage('Email invalid for {domain}')
            ->withContext(['domain' => 'example.com'])
            ->forField('email');
        
        $arr = $error->toArray();
        
        self::assertSame('invalid_email', $arr['code']);
        self::assertSame('Email invalid for {domain}', $arr['message']);
        self::assertSame('email', $arr['field']);
        self::assertArrayHasKey('domain', $arr['context']);
    }
    
    // ---------------------------------------------------------------------
    // Edge Cases
    // ---------------------------------------------------------------------
    
    public function testInterpolateHandlesNumericAndBooleanValues(): void
    {
        $error = new ErrorDefinition('test', 'Got {count}, ok={ok}', ['count' => 5, 'ok' => true]);
        self::assertSame('Got 5, ok=1', $error->interpolate());
    }
    
    public function testWithContextOverwritesDuplicateKeys(): void
    {
        $original = new ErrorDefinition('range', 'Out of range', ['min' => 1]);
        $updated = $original->withContext(['min' => 5]);
        
        self::assertSame(5, $updated->getContext()['min']);
    }
}
