<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Transformer;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Transformer\CallbackTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Transformer\CallbackTransformer
 */
final class CallbackTransformerTest extends TestCase
{
    private FieldDefinition $field;
    private FormContext $context;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->field = new FieldDefinition('name');
        $this->context = new FormContext();
    }
    
    // -------------------------------------------------
    // Data-driven tests
    // -------------------------------------------------
    
    #[DataProvider('provideCallableVariants')]
    public function testExecutesVariousCallableTypes(callable $callback, mixed $input, mixed $expected): void
    {
        $transformer = new CallbackTransformer($callback);
        $result = $transformer->apply($input, $this->field, $this->context);
        
        $this->assertSame($expected, $result);
    }
    
    public static function provideCallableVariants(): array
    {
        $helper = new class {
            public function dashify(string $v): string
            {
                return str_replace(' ', '-', $v);
            }
        };
        
        return [
            'closure lowercase' => [
                fn($v) => strtolower((string)$v),
                'ABC',
                'abc',
            ],
            'object method callable' => [
                [$helper, 'dashify'](...),
                'hello world',
                'hello-world',
            ],
            'callable ignoring field arg' => [
                fn($v) => "value:{$v}",
                'X',
                'value:X',
            ],
            'callable handling field arg' => [
                function ($v, FieldDefinition $f): string {
                    return strtoupper((string)$v) . '-' . $f->getName();
                },
                'abc',
                'ABC-name',
            ],
        ];
    }
    
    // -------------------------------------------------
    // Field passing and idempotency
    // -------------------------------------------------
    
    public function testReceivesFieldDefinitionInstance(): void
    {
        $called = false;
        
        $transformer = new CallbackTransformer(
            function ($value, FieldDefinition $field, FormContext $context) use (&$called) {
                $called = true;
                $this->assertInstanceOf(FieldDefinition::class, $field);
                $this->assertInstanceOf(FormContext::class, $context);
                $this->assertSame('test-value', $value);
                return 'ok';
            }
        );
        
        $result = $transformer->apply('test-value', $this->field, $this->context);
        
        $this->assertTrue($called, 'Callback should be executed');
        $this->assertSame('ok', $result);
    }
    
    #[DataProvider('provideIdempotentCases')]
    public function testIdempotency(callable $callback, mixed $input): void
    {
        $transformer = new CallbackTransformer($callback);
        $once = $transformer->apply($input, $this->field, $this->context);
        $twice = $transformer->apply($once, $this->field, $this->context);
        
        $this->assertSame($once, $twice, 'Transformer should be idempotent');
    }
    
    public static function provideIdempotentCases(): array
    {
        return [
            'trim' => [fn($v) => trim((string)$v), '  abc  '],
            'normalize-dash' => [fn($v) => str_replace('--', '-', (string)$v), 'a--b'],
        ];
    }
    
    // -------------------------------------------------
    // Edge cases
    // -------------------------------------------------
    
    #[DataProvider('provideEdgeCases')]
    public function testEdgeCases(callable $callback, mixed $input, mixed $expected): void
    {
        $transformer = new CallbackTransformer($callback);
        $this->assertSame($expected, $transformer->apply($input, $this->field, $this->context));
    }
    
    public static function provideEdgeCases(): array
    {
        return [
            'null value handled' => [fn($v) => $v ?? 'empty', null, 'empty'],
            'numeric value passthrough' => [fn($v) => $v, 123, 123],
            'array passthrough' => [fn($v) => $v, ['x'], ['x']],
        ];
    }
    
    // -------------------------------------------------
    // Invalid callable
    // -------------------------------------------------
    
    public static function provideValidCallableCases(): array
    {
        return [
            'single-arg callable works' => ['strtoupper'(...), 'abc', 'ABC'],
        ];
    }
    
    #[DataProvider('provideValidCallableCases')]
    public function testSingleArgCallableWorks(callable $callback, string $input, string $expected): void
    {
        $transformer = new CallbackTransformer($callback);
        $result = $transformer->apply($input, $this->field, $this->context);
        $this->assertSame($expected, $result);
    }
}
