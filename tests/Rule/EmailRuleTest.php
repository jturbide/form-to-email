<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationResult;
use FormToEmail\Rule\EmailRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Rule\EmailRule
 *
 * Validates email rule behavior for ASCII, IDN, and Unicode (RFC 6531) cases.
 */
final class EmailRuleTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('email');
    }
    
    // ---------------------------------------------------------
    // Data Providers
    // ---------------------------------------------------------
    
    public static function validEmailData(): array
    {
        return [
            'simple ascii' => ['user@example.com', true],
            'mixed case' => ['User.Name@Example.COM', true],
            'idn domain (Unicode)' => ['user@exämple.com', true],
            'unicode local part (RFC 6531)' => ['élodie@domain.com', true],
        ];
    }
    
    public static function invalidEmailData(): array
    {
        return [
            'missing domain' => ['user@', false],
            'missing @' => ['userexample.com', false],
            'multiple @' => ['a@b@c.com', false],
            'invalid symbols' => ['user!@@example.com', false],
            'invalid domain chars' => ['user@exa mple.com', false],
        ];
    }
    
    // ---------------------------------------------------------
    // Basic Success
    // ---------------------------------------------------------
    
    #[DataProvider('validEmailData')]
    public function testValidEmails(string $email, bool $expected): void
    {
        $rule = new EmailRule();
        $ctx = new FormContext(['email' => $email]);
        
        $rule->process($email, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertSame($expected, $result->valid);
        $this->assertSame([], $result->getErrors('email'));
    }
    
    // ---------------------------------------------------------
    // Basic Failures
    // ---------------------------------------------------------
    
    #[DataProvider('invalidEmailData')]
    public function testInvalidEmails(string $email, bool $expected): void
    {
        $rule = new EmailRule();
        $ctx = new FormContext(['email' => $email]);
        
        $rule->process($email, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertSame($expected, $result->valid);
        $this->assertFalse($result->valid);
        
        $error = $result->firstError('email');
        $this->assertInstanceOf(ErrorDefinition::class, $error);
        $this->assertSame('invalid_email', $error->getCode());
    }
    
    // ---------------------------------------------------------
    // Empty Values
    // ---------------------------------------------------------
    
    public function testEmptyValueIsConsideredValid(): void
    {
        $rule = new EmailRule();
        $ctx = new FormContext(['email' => '']);
        
        $rule->process('', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertTrue($result->valid);
    }
    
    // ---------------------------------------------------------
    // IDN Normalization
    // ---------------------------------------------------------
    
    public function testIdnNormalization(): void
    {
        $rule = new EmailRule();
        $ctx = new FormContext(['email' => 'user@exämple.com']);
        
        $rule->process('user@exämple.com', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertTrue($result->valid);
    }
    
    // ---------------------------------------------------------
    // Strict ASCII Mode
    // ---------------------------------------------------------
    
    public function testDisallowsUnicodeWhenDisabled(): void
    {
        $rule = new EmailRule(allowUnicode: false);
        $ctx = new FormContext(['email' => 'élodie@domain.com']);
        
        $rule->process('élodie@domain.com', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertFalse($result->valid);
    }
    
    // ---------------------------------------------------------
    // Structured Error Context
    // ---------------------------------------------------------
    
    public function testErrorIncludesOriginalAndNormalizedValues(): void
    {
        $rule = new EmailRule();
        $ctx = new FormContext(['email' => 'invalid@@example.com']);
        
        $rule->process('invalid@@example.com', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $error = $result->firstError('email');
        $this->assertInstanceOf(ErrorDefinition::class, $error);
        $this->assertArrayHasKey('value', $error->getContext());
        $this->assertArrayHasKey('normalized', $error->getContext());
    }
}
