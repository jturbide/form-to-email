<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationError;
use FormToEmail\Core\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Core\ValidationResult
 * @covers \FormToEmail\Core\ErrorDefinition
 *
 * Comprehensive test suite for the immutable ValidationResult class.
 */
final class ValidationResultTest extends TestCase
{
    private ValidationError $error1;
    private ValidationError $error2;
    
    protected function setUp(): void
    {
        $this->error1 = new ErrorDefinition('required', 'This field is required', field: 'email');
        $this->error2 = new ErrorDefinition('too_short', 'Min {min}', ['min' => 3], 'password');
    }
    
    // ---------------------------------------------------------------------
    // Construction and predicates
    // ---------------------------------------------------------------------
    
    public function testBasicPropertiesAndPredicates(): void
    {
        $data = ['email' => 'user@example.com'];
        $errors = ['password' => [$this->error2]];
        
        $result = new ValidationResult(false, $errors, $data);
        
        self::assertFalse($result->isValid());
        self::assertTrue($result->failed());
        self::assertTrue($result->hasError('password'));
        self::assertFalse($result->hasError('email'));
        
        self::assertSame($errors, $result->allErrors());
        self::assertSame($data, $result->allData());
    }
    
    public function testValidResultHasNoErrors(): void
    {
        $res = new ValidationResult(true, [], ['ok' => true]);
        self::assertTrue($res->isValid());
        self::assertFalse($res->failed());
        self::assertFalse($res->hasError('anything'));
        self::assertSame(['ok' => true], $res->allData());
    }
    
    // ---------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------
    
    public function testGetErrorsAndFirstError(): void
    {
        $res = new ValidationResult(false, ['email' => [$this->error1]], []);
        $errs = $res->getErrors('email');
        
        self::assertCount(1, $errs);
        self::assertSame($this->error1, $errs[0]);
        self::assertSame($this->error1, $res->firstError('email'));
        self::assertNull($res->firstError('unknown'));
    }
    
    // ---------------------------------------------------------------------
    // Factory method
    // ---------------------------------------------------------------------
    
    public function testFromContextBuildsEquivalentResult(): void
    {
        $ctx = new FormContext(['email' => 'a']);
        $ctx->addError('email', $this->error1);
        $ctx->setValue('email', 'normalized');
        
        $res = ValidationResult::fromContext($ctx);
        
        self::assertInstanceOf(ValidationResult::class, $res);
        self::assertFalse($res->isValid());
        self::assertArrayHasKey('email', $res->allErrors());
        self::assertSame('normalized', $res->allData()['email']);
    }
    
    // ---------------------------------------------------------------------
    // Interpolation and messages
    // ---------------------------------------------------------------------
    
    public function testInterpolateReturnsMessageWhenNoPlaceholders(): void
    {
        $res = new ValidationResult(false, [], []);
        $err = new ErrorDefinition('code', 'plain message');
        self::assertSame('plain message', $res->interpolate($err));
    }
    
    public function testInterpolateFallsBackToCodeWhenMessageEmpty(): void
    {
        $res = new ValidationResult(false, [], []);
        $err = new ErrorDefinition('missing_message', '');
        self::assertSame('missing_message', $res->interpolate($err));
    }
    
    public function testInterpolateReplacesContextVariables(): void
    {
        $res = new ValidationResult(false, [], []);
        $err = new ErrorDefinition('range', 'Between {min} and {max}', ['min' => 1, 'max' => 5]);
        self::assertSame('Between 1 and 5', $res->interpolate($err));
    }
    
    public function testMessagesAndMessagesAllReturnInterpolatedStrings(): void
    {
        $errors = [
            'password' => [$this->error2],
            'email' => [$this->error1],
        ];
        $res = new ValidationResult(false, $errors, []);
        
        $msgs = $res->messages('password');
        self::assertSame(['Min 3'], $msgs);
        
        $all = $res->messagesAll();
        self::assertSame(['Min 3'], $all['password']);
        self::assertSame(['This field is required'], $all['email']);
    }
    
    // ---------------------------------------------------------------------
    // Serialization
    // ---------------------------------------------------------------------
    
    public function testToArrayNonInterpolated(): void
    {
        $res = new ValidationResult(false, ['email' => [$this->error1]], ['foo' => 'bar']);
        $arr = $res->toArray(false);
        
        self::assertSame([
            'valid' => false,
            'errors' => [
                'email' => [[
                    'code' => 'required',
                    'message' => 'This field is required',
                    'context' => [],
                    'field' => 'email',
                ]],
            ],
            'data' => ['foo' => 'bar'],
        ], $arr);
    }
    
    public function testToArrayInterpolated(): void
    {
        $res = new ValidationResult(false, ['password' => [$this->error2]], []);
        $arr = $res->toArray(true);
        
        self::assertSame([
            'valid' => false,
            'errors' => [
                'password' => ['Min 3'],
            ],
            'data' => [],
        ], $arr);
    }
    
    public function testJsonSerializeMatchesToArray(): void
    {
        $res = new ValidationResult(false, ['email' => [$this->error1]], ['x' => 1]);
        $json = json_encode($res, JSON_THROW_ON_ERROR);
        
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($res->toArray(false), $decoded);
    }
    
    // ---------------------------------------------------------------------
    // Structural integrity
    // ---------------------------------------------------------------------
    
    public function testResultIsReadonlyAndImmutable(): void
    {
        $res = new ValidationResult(true, [], []);
        $reflection = new \ReflectionClass($res);
        
        foreach ($reflection->getProperties() as $prop) {
            self::assertTrue($prop->isReadOnly(), "Property {$prop->getName()} must be readonly");
        }
        
        // Confirm modification attempt fails
        $this->expectException(\Error::class);
        $res->valid = false; // should throw
    }
}
