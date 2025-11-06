<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationError;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Core\FormContext
 *
 * Comprehensive tests for the form context lifecycle:
 *  - input and data access
 *  - mutation helpers (values and errors)
 *  - global and field-specific error handling
 *  - immutability clone helpers
 */
final class FormContextTest extends TestCase
{
    private ValidationError $sampleError;
    
    protected function setUp(): void
    {
        $this->sampleError = new ErrorDefinition('required', 'Field is required');
    }
    
    // ---------------------------------------------------------------------
    // Construction & Input/Data access
    // ---------------------------------------------------------------------
    
    public function testConstructorInitializesValues(): void
    {
        $input = ['email' => 'user@example.com'];
        $data = ['email' => 'normalized@example.com'];
        $errors = ['email' => [$this->sampleError]];
        
        $ctx = new FormContext($input, $data, $errors);
        
        self::assertSame('user@example.com', $ctx->getInput('email'));
        self::assertSame('normalized@example.com', $ctx->getValue('email'));
        self::assertSame($input, $ctx->allInput());
        self::assertSame($data, $ctx->allData());
        self::assertSame($errors, $ctx->allErrors());
    }
    
    public function testGetValueFallsBackToInput(): void
    {
        $ctx = new FormContext(['name' => 'John']);
        self::assertSame('John', $ctx->getValue('name'));
    }
    
    public function testSetValueUpdatesNormalizedData(): void
    {
        $ctx = new FormContext(['email' => 'Raw']);
        $ctx->setValue('email', 'Normalized');
        
        self::assertSame('Normalized', $ctx->getValue('email'));
        self::assertArrayHasKey('email', $ctx->allData());
    }
    
    public function testGetUnknownFieldsReturnNull(): void
    {
        $ctx = new FormContext();
        self::assertNull($ctx->getInput('unknown'));
        self::assertNull($ctx->getValue('unknown'));
    }
    
    // ---------------------------------------------------------------------
    // Error management
    // ---------------------------------------------------------------------
    
    public function testAddErrorCreatesErrorList(): void
    {
        $ctx = new FormContext();
        $ctx->addError('email', $this->sampleError);
        
        $errors = $ctx->getFieldErrors('email');
        self::assertCount(1, $errors);
        self::assertInstanceOf(ValidationError::class, $errors[0]);
        self::assertTrue($ctx->hasError('email'));
        self::assertTrue($ctx->hasAnyErrors());
    }
    
    public function testAddMultipleErrorsToSameField(): void
    {
        $ctx = new FormContext();
        $err1 = new ErrorDefinition('a', 'msg1');
        $err2 = new ErrorDefinition('b', 'msg2');
        
        $ctx->addError('field', $err1);
        $ctx->addError('field', $err2);
        
        $errors = $ctx->getFieldErrors('field');
        self::assertCount(2, $errors);
        self::assertSame('a', $errors[0]->getCode());
        self::assertSame('b', $errors[1]->getCode());
    }
    
    public function testAddGlobalErrorStoresUnderGlobalField(): void
    {
        $ctx = new FormContext();
        $ctx->addGlobalError($this->sampleError);
        
        $errors = $ctx->getFieldErrors(FormContext::GLOBAL_FIELD);
        self::assertCount(1, $errors);
        self::assertSame('required', $errors[0]->getCode());
    }
    
    public function testHasAnyErrorsDetectsEmptyAndNonEmptyStates(): void
    {
        $ctx = new FormContext();
        self::assertFalse($ctx->hasAnyErrors());
        
        $ctx->addError('f', $this->sampleError);
        self::assertTrue($ctx->hasAnyErrors());
    }
    
    public function testGetFieldErrorsReturnsEmptyArrayIfNone(): void
    {
        $ctx = new FormContext();
        self::assertSame([], $ctx->getFieldErrors('missing'));
    }
    
    public function testSetAllErrorsReplacesExisting(): void
    {
        $ctx = new FormContext();
        $err = new ErrorDefinition('x', 'y');
        $ctx->setAllErrors(['a' => [$err]]);
        
        $errors = $ctx->allErrors();
        self::assertArrayHasKey('a', $errors);
        self::assertSame($err, $errors['a'][0]);
    }
    
    public function testClearFieldErrorsRemovesSpecificField(): void
    {
        $ctx = new FormContext([], [], ['x' => [$this->sampleError]]);
        $ctx->clearFieldErrors('x');
        self::assertSame([], $ctx->allErrors());
    }
    
    public function testClearAllErrorsRemovesEverything(): void
    {
        $ctx = new FormContext([], [], [
            'a' => [$this->sampleError],
            'b' => [$this->sampleError],
        ]);
        
        $ctx->clearAllErrors();
        self::assertSame([], $ctx->allErrors());
        self::assertFalse($ctx->hasAnyErrors());
    }
    
    // ---------------------------------------------------------------------
    // Immutability clone helpers
    // ---------------------------------------------------------------------
    
    public function testWithValueCreatesClonedInstance(): void
    {
        $ctx = new FormContext(['name' => 'raw']);
        $new = $ctx->withValue('name', 'new');
        
        self::assertNotSame($ctx, $new);
        self::assertSame('new', $new->getValue('name'));
        self::assertSame('raw', $ctx->getValue('name'));
    }
    
    public function testWithErrorCreatesClonedInstanceWithExtraError(): void
    {
        $ctx = new FormContext();
        $clone = $ctx->withError('email', $this->sampleError);
        
        self::assertNotSame($ctx, $clone);
        self::assertFalse($ctx->hasError('email'));
        self::assertTrue($clone->hasError('email'));
    }
    
    // ---------------------------------------------------------------------
    // Complex combination behavior
    // ---------------------------------------------------------------------
    
    public function testMultipleOperationsKeepInternalStateConsistent(): void
    {
        $ctx = new FormContext(['name' => 'raw']);
        $ctx->setValue('name', 'filtered');
        $ctx->addError('name', $this->sampleError);
        
        self::assertSame('filtered', $ctx->getValue('name'));
        self::assertTrue($ctx->hasError('name'));
        
        // clone and clear
        $clone = $ctx->withValue('name', 'new');
        $clone->clearAllErrors();
        
        self::assertSame('new', $clone->getValue('name'));
        self::assertFalse($clone->hasAnyErrors());
        self::assertTrue($ctx->hasAnyErrors());
    }
}
