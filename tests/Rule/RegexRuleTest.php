<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationResult;
use FormToEmail\Rule\RegexRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Rule\RegexRule
 *
 * Tests the RegexRule under realistic usage scenarios,
 * validating pattern correctness and error handling.
 */
final class RegexRuleTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('username');
    }
    
    // ---------------------------------------------------------
    // Data Providers
    // ---------------------------------------------------------
    
    public static function validPatternData(): array
    {
        return [
            'letters only' => ['/^[A-Za-z]+$/u', 'John', true],
            'numbers only' => ['/^[0-9]+$/', '12345', true],
            'unicode letters' => ['/^[\p{L}]+$/u', 'Élodie', true],
            'case-insensitive match' => ['/^[a-z]+$/i', 'HELLO', true], // explicitly provide /i
        ];
    }
    
    public static function invalidPatternData(): array
    {
        return [
            'wrong pattern' => ['/^[A-Za-z]+$/u', '123', false],
            'partial match' => ['/foo/', 'bar', false],
            'invalid chars' => ['/^[0-9]+$/', 'abc', false],
        ];
    }
    
    // ---------------------------------------------------------
    // Basic Validation Success
    // ---------------------------------------------------------
    
    #[DataProvider('validPatternData')]
    public function testValidPatterns(string $pattern, string $value, bool $expected): void
    {
        $rule = new RegexRule($pattern);
        $ctx = new FormContext(['username' => $value]);
        
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertSame($expected, $result->valid);
        $this->assertSame([], $result->getErrors('username'));
    }
    
    // ---------------------------------------------------------
    // Basic Validation Failures
    // ---------------------------------------------------------
    
    #[DataProvider('invalidPatternData')]
    public function testInvalidPatterns(string $pattern, string $value, bool $expected): void
    {
        $rule = new RegexRule($pattern);
        $ctx = new FormContext(['username' => $value]);
        
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertSame($expected, $result->valid);
        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->getErrors('username'));
        
        $err = $result->firstError('username');
        $this->assertInstanceOf(ErrorDefinition::class, $err);
        $this->assertSame('invalid_format', $err->getCode());
    }
    
    // ---------------------------------------------------------
    // Empty Values Are Ignored (Optional Field)
    // ---------------------------------------------------------
    
    public function testEmptyStringIsValid(): void
    {
        $rule = new RegexRule('/^[A-Za-z]+$/');
        $ctx = new FormContext(['username' => '']);
        
        $rule->process('', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertTrue($result->valid);
        $this->assertSame([], $result->getErrors('username'));
    }
    
    // ---------------------------------------------------------
    // Multibyte Handling (developer-defined)
    // ---------------------------------------------------------
    
    public function testMultibyteValidation(): void
    {
        // Developer provides /u explicitly
        $rule = new RegexRule('/^[\p{L}]+$/u');
        $ctx = new FormContext(['username' => 'Łukasz']);
        
        $rule->process('Łukasz', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertTrue($result->valid);
    }
    
    // ---------------------------------------------------------
    // Case Sensitivity (developer-defined)
    // ---------------------------------------------------------
    
    public function testCaseInsensitiveMatching(): void
    {
        // Developer provides /i explicitly
        $rule = new RegexRule('/^[a-z]+$/i');
        $ctx = new FormContext(['username' => 'HELLO']);
        
        $rule->process('HELLO', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertTrue($result->valid);
    }
    
    // ---------------------------------------------------------
    // Invalid Pattern Detection
    // ---------------------------------------------------------
    
    public function testThrowsOnInvalidPattern(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RegexRule('/[unclosed/');
    }
    
    // ---------------------------------------------------------
    // Error Definition Structure
    // ---------------------------------------------------------
    
    public function testErrorDefinitionStructure(): void
    {
        $rule = new RegexRule('/^[0-9]+$/', 'invalid_numeric');
        $ctx = new FormContext(['username' => 'ABC']);
        
        $rule->process('ABC', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $error = $result->firstError('username');
        $this->assertInstanceOf(ErrorDefinition::class, $error);
        $this->assertSame('invalid_numeric', $error->getCode());
        $this->assertSame('username', $error->getField());
        $this->assertArrayHasKey('pattern', $error->getContext());
        $this->assertArrayHasKey('value', $error->getContext());
    }
    
    // ---------------------------------------------------------
    // Defensive Behavior
    // ---------------------------------------------------------
    
    public function testHandlesNonStringInputGracefully(): void
    {
        $rule = new RegexRule('/^[0-9]+$/');
        $ctx = new FormContext(['username' => 12345]);
        
        $rule->process(12345, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertTrue($result->valid);
        $this->assertSame([], $result->getErrors('username'));
    }
}
