<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use Closure;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\CallbackFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\CallbackFilter
 *
 * Comprehensive suite for verifying dynamic callback execution,
 * argument passing, type safety, and exception propagation.
 */
final class CallbackFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('fieldName');
    }
    
    // --------------------------------------------------------
    // BASIC FUNCTIONALITY
    // --------------------------------------------------------
    
    public function testExecutesAnonymousClosure(): void
    {
        $filter = new CallbackFilter(fn ($v) => strtoupper((string)$v));
        $this->assertSame('HELLO', $filter->apply('hello', $this->field));
    }
    
    public function testExecutesNamedFunction(): void
    {
        // Wrap simple named function inside closure to fit the two-arg contract
        $filter = new CallbackFilter(fn ($v) => strtolower((string)$v));
        $this->assertSame('test', $filter->apply('TEST', $this->field));
    }
    
    public function testExecutesStaticMethod(): void
    {
        $filter = new CallbackFilter([self::class, 'reverseWithField']);
        $this->assertSame('dcba', $filter->apply('abcd', $this->field));
    }
    
    public static function reverseWithField(mixed $value, FieldDefinition $field): string
    {
        return strrev((string)$value);
    }
    
    // --------------------------------------------------------
    // FIELD DEFINITION INJECTION
    // --------------------------------------------------------
    
    public function testReceivesFieldDefinitionInstance(): void
    {
        $called = false;
        
        $filter = new CallbackFilter(function ($value, FieldDefinition $field) use (&$called) {
            $called = true;
            $this->assertInstanceOf(FieldDefinition::class, $field);
            $this->assertSame('email', $field->getName());
            return strtoupper((string)$value);
        });
        
        $output = $filter->apply('foo', new FieldDefinition('email'));
        
        $this->assertTrue($called, 'Callback should have been executed');
        $this->assertSame('FOO', $output);
    }
    
    // --------------------------------------------------------
    // DATA-DRIVEN BEHAVIOR
    // --------------------------------------------------------
    
    #[DataProvider('provideCallbacks')]
    public function testExecutesVariousCallbacks(callable $callable, mixed $input, mixed $expected): void
    {
        $filter = new CallbackFilter($callable);
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public static function provideCallbacks(): array
    {
        return [
            'trim spaces' => [fn($v) => trim((string)$v), '  hi  ', 'hi'],
            'append field name' => [fn($v, FieldDefinition $f) => $v . '-' . $f->getName(), 'val', 'val-fieldName'],
            'return number unchanged' => [fn($v) => $v, 42, 42],
            'handle null' => [fn($v) => $v, null, null],
            'uppercase unicode' => [fn($v) => mb_strtoupper((string)$v, 'UTF-8'), 'héllo', 'HÉLLO'],
        ];
    }
    
    // --------------------------------------------------------
    // OBJECT AND INVOKABLE HANDLING
    // --------------------------------------------------------
    
    public function testSupportsInvokableObject(): void
    {
        $callableObject = new class {
            public function __invoke(mixed $v, FieldDefinition $f): mixed
            {
                return '[' . strtoupper((string)$v) . ']';
            }
        };
        
        $filter = new CallbackFilter($callableObject);
        $this->assertSame('[HELLO]', $filter->apply('hello', $this->field));
    }
    
    public function testSupportsFirstClassCallableSyntax(): void
    {
        $callable = self::reverseWithField(...);
        $filter = new CallbackFilter($callable);
        $this->assertSame('321', $filter->apply('123', $this->field));
    }
    
    // --------------------------------------------------------
    // EXCEPTION AND STATE PROPAGATION
    // --------------------------------------------------------
    
    public function testCallbackCanThrowExceptionAndPropagate(): void
    {
        $filter = new CallbackFilter(function (): void {
            throw new \RuntimeException('Intentional error');
        });
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Intentional error');
        $filter->apply('x', $this->field);
    }
    
    public function testStatefulClosureMaintainsCapturedVariables(): void
    {
        $suffix = '-ok';
        $filter = new CallbackFilter(fn($v) => $v . $suffix);
        $this->assertSame('value-ok', $filter->apply('value', $this->field));
    }
    
    // --------------------------------------------------------
    // EDGE CASES AND VALIDATION
    // --------------------------------------------------------
    
    public function testCanHandleNonStringTypesGracefully(): void
    {
        $filter = new CallbackFilter(fn($v) => is_array($v) ? implode(',', $v) : (string)$v);
        
        $this->assertSame('a,b,c', $filter->apply(['a', 'b', 'c'], $this->field));
        $this->assertSame('123', $filter->apply(123, $this->field));
        $this->assertSame('', $filter->apply(null, $this->field));
    }
    
    public function testNestedCallbackExecution(): void
    {
        $inner = fn($v) => trim($v);
        $outer = fn($v, FieldDefinition $f) => strtoupper($inner($v)) . '-' . $f->getName();
        
        $filter = new CallbackFilter($outer);
        $this->assertSame('ABC-fieldName', $filter->apply(' abc ', $this->field));
    }
    
    public function testCallbackReturningReferenceValue(): void
    {
        $callback = function (&$v, FieldDefinition $f) {
            $v = strtoupper((string)$v);
            return $v;
        };
        
        $filter = new CallbackFilter($callback);
        $val = 'mixed';
        $result = $filter->apply($val, $this->field);
        $this->assertSame('MIXED', $result);
    }
    
    public function testNestedAnonymousFunctionsChain(): void
    {
        $filter = new CallbackFilter(
            fn($v) => ucfirst(trim((fn($x) => strtolower($x))($v)))
        );
        
        $this->assertSame('Hello', $filter->apply('  HELLO ', $this->field));
    }
    
    public function testCallbackCanReturnObjects(): void
    {
        $filter = new CallbackFilter(fn($v) => (object)['data' => $v]);
        $result = $filter->apply('test', $this->field);
        
        $this->assertIsObject($result);
        $this->assertSame('test', $result->data);
    }
}
