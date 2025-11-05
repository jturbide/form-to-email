<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Rule;

use Closure;
use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\ValidationResult;
use FormToEmail\Rule\CallbackRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Rule\CallbackRule
 *
 * Tests the flexible, developer-defined callback validation mechanism.
 */
final class CallbackRuleTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('test');
    }
    
    // ---------------------------------------------------------
    // Data Providers
    // ---------------------------------------------------------
    
    public static function validCallbackData(): array
    {
        return [
            'simple pass' => [static fn($v): array => [], 'anything', true],
            'conditional pass' => [static fn($v): array => $v === 'A' ? [] : ['must_be_A'], 'A', true],
        ];
    }
    
    public static function invalidCallbackData(): array
    {
        return [
            'simple fail' => [static fn($v): array => ['failed'], 'x', false],
            'multi error' => [static fn($v): array => ['a', 'b'], 'y', false],
        ];
    }
    
    // ---------------------------------------------------------
    // Basic Behavior
    // ---------------------------------------------------------
    
    #[DataProvider('validCallbackData')]
    public function testCallbackRuleValidations(Closure $validator, string $value, bool $expected): void
    {
        $rule = new CallbackRule($validator);
        $ctx = new FormContext(['test' => $value]);
        $rule->process($value, $this->field, $ctx);
        
        $result = ValidationResult::fromContext($ctx);
        $this->assertSame($expected, $result->valid);
        $this->assertSame([], $result->getErrors('test'));
    }
    
    #[DataProvider('invalidCallbackData')]
    public function testCallbackRuleFailures(Closure $validator, string $value, bool $expected): void
    {
        $rule = new CallbackRule($validator);
        $ctx = new FormContext(['test' => $value]);
        $rule->process($value, $this->field, $ctx);
        
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertSame($expected, $result->valid);
        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->getErrors('test'));
    }
    
    // ---------------------------------------------------------
    // Structured ErrorDefinition support
    // ---------------------------------------------------------
    
    public function testCallbackRuleAcceptsErrorDefinition(): void
    {
        $rule = new CallbackRule(static function (): array {
            return [
                new ErrorDefinition(
                    code: 'custom',
                    message: 'Custom structured error',
                    field: 'test'
                )
            ];
        });
        
        $ctx = new FormContext(['test' => 'foo']);
        $rule->process('foo', $this->field, $ctx);
        $result = ValidationResult::fromContext($ctx);
        
        $this->assertFalse($result->valid);
        $err = $result->firstError('test');
        $this->assertInstanceOf(ErrorDefinition::class, $err);
        $this->assertSame('custom', $err->getCode());
        $this->assertSame('Custom structured error', $err->getMessage());
    }
    
    // ---------------------------------------------------------
    // Context-Aware Callback
    // ---------------------------------------------------------
    
    public function testCallbackRuleWithContextAccess(): void
    {
        $rule = new CallbackRule(static function (mixed $value, FieldDefinition $field, FormContext $ctx): array {
            $confirm = $ctx->getValue('confirm');
            return $value === $confirm ? [] : ['mismatch'];
        });
        
        // Pass
        $ctx1 = new FormContext(['test' => 'secret', 'confirm' => 'secret']);
        $rule->process('secret', $this->field, $ctx1);
        $result1 = ValidationResult::fromContext($ctx1);
        $this->assertTrue($result1->valid);
        
        // Fail
        $ctx2 = new FormContext(['test' => 'secret', 'confirm' => 'wrong']);
        $rule->process('secret', $this->field, $ctx2);
        $result2 = ValidationResult::fromContext($ctx2);
        $this->assertFalse($result2->valid);
        
        $err = $result2->firstError('test');
        $this->assertSame('mismatch', $err->getCode());
    }
    
    // ---------------------------------------------------------
    // Defensive checks
    // ---------------------------------------------------------
    
    public function testCallbackThrowsOnInvalidReturnType(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $rule = new CallbackRule(static fn() => 'invalid_return');
        $ctx = new FormContext(['test' => 'foo']);
        $rule->process('foo', $this->field, $ctx);
    }
    
    public function testCallbackThrowsOnInvalidErrorType(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $rule = new CallbackRule(static fn() => [123]);
        $ctx = new FormContext(['test' => 'foo']);
        $rule->process('foo', $this->field, $ctx);
    }
    
    // ---------------------------------------------------------
    // Integration-like test
    // ---------------------------------------------------------
    
    public function testCallbackRuleProducesNormalizedErrorStructure(): void
    {
        $rule = new CallbackRule(static fn() => ['invalid']);
        $ctx = new FormContext(['test' => 'foo']);
        $rule->process('foo', $this->field, $ctx);
        
        $result = ValidationResult::fromContext($ctx);
        $array = $result->toArray();
        
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('test', $array['errors']);
        $error = $array['errors']['test'][0];
        $this->assertSame('invalid', $error['code']);
        $this->assertArrayHasKey('context', $error);
        $this->assertSame('test', $error['context']['field']);
    }
}
