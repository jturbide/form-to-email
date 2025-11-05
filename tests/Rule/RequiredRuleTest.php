<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Rule;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationResult;
use FormToEmail\Core\ValidationError;
use FormToEmail\Rule\RequiredRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Rule\RequiredRule
 * @covers \FormToEmail\Core\ErrorDefinition
 *
 * Verifies the RequiredRule logic, including its integration
 * with FormContext and ValidationResult, using PHP 8.4+ style.
 */
final class RequiredRuleTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('username');
    }
    
    // ---------------------------------------------------------
    // Data Providers
    // ---------------------------------------------------------
    
    public static function validValues(): array
    {
        return [
            'non-empty string' => ['Julien'],
            'zero integer'     => [0],
            'non-empty array'  => [['a']],
            'object'           => [(object)['x' => 1]],
        ];
    }
    
    public static function invalidValues(): array
    {
        return [
            'empty string'     => [''],
            'whitespace only'  => ['   '],
            'null'             => [null],
            'false'            => [false],
            'empty array'      => [[]],
        ];
    }
    
    // ---------------------------------------------------------
    // Tests
    // ---------------------------------------------------------
    
    #[DataProvider('validValues')]
    public function testValidValuesAreAccepted(mixed $value): void
    {
        $rule = new RequiredRule();
        $ctx = new FormContext(['username' => $value]);
        
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertTrue(
            $result->valid,
            sprintf('Expected "%s" to be valid', var_export($value, true))
        );
        $this->assertFalse($result->hasError('username'));
        $this->assertSame([], $result->getErrors('username'));
    }
    
    #[DataProvider('invalidValues')]
    public function testInvalidValuesProduceRequiredError(mixed $value): void
    {
        $rule = new RequiredRule();
        $ctx = new FormContext(['username' => $value]);
        
        $rule->process($value, $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertFalse(
            $result->valid,
            sprintf('Expected "%s" to be invalid', var_export($value, true))
        );
        $this->assertTrue($result->hasError('username'));
        
        $errors = $result->getErrors('username');
        $this->assertCount(1, $errors, 'Expected exactly one error');
        $this->assertInstanceOf(ValidationError::class, $errors[0]);
        $this->assertSame('required', $errors[0]->getCode());
        $this->assertSame('username', $errors[0]->getField());
    }
    
    public function testCustomErrorCodeAndMessageAreRespected(): void
    {
        $rule = new RequiredRule(
            errorCode: 'missing_field',
            message: 'Please fill out {field}.'
        );
        
        $ctx = new FormContext(['username' => '']);
        $rule->process('', $this->field, $ctx);
        
        $result = ValidationResult::fromContext($ctx);
        $error = $result->firstError('username');
        
        $this->assertNotNull($error);
        $this->assertSame('missing_field', $error->getCode());
        $this->assertSame('Please fill out {field}.', $error->getMessage());
        $this->assertSame(['field' => 'username'], $error->getContext());
        
        $this->assertSame(
            'Please fill out username.',
            $result->interpolate($error)
        );
    }
    
    public function testStructuredErrorSerializationIntegrity(): void
    {
        $ctx = new FormContext(['username' => ' ']);
        $rule = new RequiredRule();
        $rule->process(' ', $this->field, $ctx);
        
        $result = ValidationResult::fromContext($ctx);
        $array = $result->toArray();
        
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('username', $array['errors']);
        
        $serialized = $array['errors']['username'][0];
        $this->assertSame('required', $serialized['code']);
        $this->assertSame('The field "{field}" is required.', $serialized['message']);
        $this->assertArrayHasKey('context', $serialized);
        $this->assertArrayHasKey('field', $serialized);
        
        $this->assertIsArray($serialized['context']);
        $this->assertSame('username', $serialized['context']['field']);
    }
}
