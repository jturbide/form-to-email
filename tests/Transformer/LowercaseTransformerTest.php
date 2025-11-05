<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Transformer;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Transformer\LowercaseTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Transformer\LowercaseTransformer
 */
final class LowercaseTransformerTest extends TestCase
{
    private FieldDefinition $field;
    private FormContext $context;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->field = new FieldDefinition('text');
        $this->context = new FormContext();
    }
    
    // -------------------------------------------------
    // Unicode-aware transformation
    // -------------------------------------------------
    
    #[DataProvider('provideUnicodeAwareCases')]
    public function testUnicodeAwareLowercase(string $input, string $expected): void
    {
        $transformer = new LowercaseTransformer(unicodeAware: true);
        $this->assertSame($expected, $transformer->apply($input, $this->field, $this->context));
    }
    
    public static function provideUnicodeAwareCases(): array
    {
        return [
            'basic ASCII' => ['John.DOE@Example.COM', 'john.doe@example.com'],
            'accented characters' => ['ÉLÈVE', 'élève'],
            'mixed unicode' => ['Straße', 'straße'],
        ];
    }
    
    // -------------------------------------------------
    // Legacy (non-unicode) behavior
    // -------------------------------------------------
    
    #[DataProvider('provideLegacyCases')]
    public function testLegacyLowercaseWithoutUnicode(string $input, string $expected): void
    {
        $transformer = new LowercaseTransformer(unicodeAware: false);
        $this->assertSame($expected, $transformer->apply($input, $this->field, $this->context));
    }
    
    public static function provideLegacyCases(): array
    {
        return [
            'ascii unaffected' => ['HELLO', 'hello'],
            // Legacy strtolower() won’t handle multibyte properly
            'unicode unchanged' => ['ÉLÈVE', 'ÉlÈve'],
        ];
    }
    
    // -------------------------------------------------
    // Type safety and idempotency
    // -------------------------------------------------
    
    public function testNonStringValuesAreUntouched(): void
    {
        $transformer = new LowercaseTransformer();
        $this->assertSame(42, $transformer->apply(42, $this->field, $this->context));
        $this->assertSame(['A'], $transformer->apply(['A'], $this->field, $this->context));
        $this->assertNull($transformer->apply(null, $this->field, $this->context));
    }
    
    public function testIdempotency(): void
    {
        $transformer = new LowercaseTransformer();
        $input = 'Email@Test.COM';
        
        $once = $transformer->apply($input, $this->field, $this->context);
        $twice = $transformer->apply($once, $this->field, $this->context);
        
        $this->assertSame($once, $twice, 'Transformer should be idempotent');
    }
}
