<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationError;
use FormToEmail\Core\ValidationResult;
use FormToEmail\Rule\LengthRule;
use FormToEmail\Rule\MinLengthRule;
use FormToEmail\Rule\MaxLengthRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Rule\LengthRule
 * @covers \FormToEmail\Rule\MinLengthRule
 * @covers \FormToEmail\Rule\MaxLengthRule
 * @covers \FormToEmail\Rule\AbstractLengthRule
 *
 * Verifies length-based validation rules (combined and single-bound)
 * with both ASCII and multibyte behavior.
 */
final class LengthRulesTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('message');
    }
    
    // ---------------------------------------------------------
    // Data Providers
    // ---------------------------------------------------------
    
    public static function validLengths(): array
    {
        return [
            'exact min' => ['hello', 5, 10],
            'between min/max' => ['abcdef', 5, 10],
            'exact max' => ['abcdefghij', 5, 10],
        ];
    }
    
    public static function tooShortLengths(): array
    {
        return [
            'one short' => ['abc', 5, 10],
//            'empty string' => ['', 5, 10], // Empty values are skipped unless the field is required
        ];
    }
    
    public static function tooLongLengths(): array
    {
        return [
            'one too long' => ['abcdefghijk', 5, 10],
            'far too long' => [str_repeat('a', 50), 5, 10],
        ];
    }
    
    // ---------------------------------------------------------
    // Tests for LengthRule (combined)
    // ---------------------------------------------------------
    
    #[DataProvider('validLengths')]
    public function testLengthRuleValidCases(string $value, int $min, int $max): void
    {
        $rule = new LengthRule(min: $min, max: $max);
        $ctx = new FormContext(['message' => $value]);
        
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertTrue($result->valid, "Expected '$value' to be valid");
        $this->assertSame([], $result->getErrors('message'));
    }
    
    #[DataProvider('tooShortLengths')]
    public function testLengthRuleTooShort(string $value, int $min, int $max): void
    {
        $rule = new LengthRule(min: $min, max: $max);
        $ctx = new FormContext(['message' => $value]);
        
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertFalse($result->valid);
        $error = $result->firstError('message');
        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('too_short', $error->getCode());
        
        $msg = $result->interpolate($error);
        $this->assertStringContainsString((string) $min, $msg);
    }
    
    #[DataProvider('tooLongLengths')]
    public function testLengthRuleTooLong(string $value, int $min, int $max): void
    {
        $rule = new LengthRule(min: $min, max: $max);
        $ctx = new FormContext(['message' => $value]);
        
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertFalse($result->valid);
        $error = $result->firstError('message');
        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('too_long', $error->getCode());
        
        $msg = $result->interpolate($error);
        $this->assertStringContainsString((string) $max, $msg);
    }
    
    public function testLengthRuleIgnoresEmptyString(): void
    {
        $rule = new LengthRule(min: 5, max: 10);
        $ctx = new FormContext(['message' => '']);
        $rule->process('', $this->field, $ctx);
        
        $result = ValidationResult::fromContext($ctx);
        $this->assertTrue($result->valid, 'Empty string should be allowed');
    }
    
    // ---------------------------------------------------------
    // MinLengthRule
    // ---------------------------------------------------------
    
    public static function minLengthCases(): array
    {
        return [
            'valid min' => ['hello', 5],
            'too short' => ['hey', 5],
        ];
    }
    
    #[DataProvider('minLengthCases')]
    public function testMinLengthRuleBehavior(string $value, int $min): void
    {
        $rule = new MinLengthRule($min);
        $ctx = new FormContext(['message' => $value]);
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        if (mb_strlen($value) < $min) {
            $this->assertFalse($result->valid);
            $error = $result->firstError('message');
            $this->assertSame('too_short', $error->getCode());
        } else {
            $this->assertTrue($result->valid);
            $this->assertSame([], $result->getErrors('message'));
        }
    }
    
    // ---------------------------------------------------------
    // MaxLengthRule
    // ---------------------------------------------------------
    
    public static function maxLengthCases(): array
    {
        return [
            'valid length' => ['hello', 10],
            'too long' => [str_repeat('a', 15), 10],
        ];
    }
    
    #[DataProvider('maxLengthCases')]
    public function testMaxLengthRuleBehavior(string $value, int $max): void
    {
        $rule = new MaxLengthRule($max);
        $ctx = new FormContext(['message' => $value]);
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        if (mb_strlen($value) > $max) {
            $this->assertFalse($result->valid);
            $error = $result->firstError('message');
            $this->assertSame('too_long', $error->getCode());
        } else {
            $this->assertTrue($result->valid);
            $this->assertSame([], $result->getErrors('message'));
        }
    }
    
    // ---------------------------------------------------------
    // Multibyte Tests
    // ---------------------------------------------------------
    
    public function testLengthRuleCountsMultibyteCharactersProperly(): void
    {
        // 3 characters, but 6 bytes in UTF-8 ("é" = 2 bytes)
        $value = 'ééé';
        $asciiRule = new LengthRule(min: 4, max: 10, multibyte: false);
        $utf8Rule  = new LengthRule(min: 4, max: 10, multibyte: true);
        
        $ctxA = new FormContext(['message' => $value]);
        $ctxB = new FormContext(['message' => $value]);
        
        $asciiRule->process($value, $this->field, $ctxA);
        $utf8Rule->process($value, $this->field, $ctxB);
        
        $resultA = ValidationResult::fromContext($ctxA);
        $resultB = ValidationResult::fromContext($ctxB);
        
        // ASCII counts 6 bytes -> passes min:4
        $this->assertTrue($resultA->valid);
        
        // UTF-8 counts 3 chars -> fails min:4
        $this->assertFalse($resultB->valid);
        $error = $resultB->firstError('message');
        $this->assertSame('too_short', $error->getCode());
    }
    
    public function testLengthRuleSerializationIntegrity(): void
    {
        $rule = new LengthRule(min: 5, max: 8);
        $ctx = new FormContext(['message' => 'abc']);
        $rule->process('abc', $this->field, $ctx);
        
        $result = ValidationResult::fromContext($ctx);
        $this->assertFalse($result->valid);
        
        $array = $result->toArray();
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('message', $array['errors']);
        $this->assertIsArray($array['errors']['message'][0]);
        
        $err = $array['errors']['message'][0];
        $this->assertSame('too_short', $err['code']);
        $this->assertArrayHasKey('context', $err);
        $this->assertSame('message', $err['context']['field']);
        $this->assertArrayHasKey('min', $err['context']);
        $this->assertArrayHasKey('length', $err['context']);
    }
}
