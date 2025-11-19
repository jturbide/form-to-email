<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Transformer;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FormContext;
use FormToEmail\Transformer\HtmlEntitiesTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Transformer\HtmlEntitiesTransformer
 */
final class HtmlEntitiesTransformerTest extends TestCase
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
    // Base escaping behavior
    // -------------------------------------------------
    
    #[DataProvider('provideEscapingCases')]
    public function testEscaping(string $input, string $expected): void
    {
        $transformer = new HtmlEntitiesTransformer();
        $this->assertSame($expected, $transformer->apply($input, $this->field, $this->context));
    }
    
    public static function provideEscapingCases(): array
    {
        return [
            'html tags' => [
                '<b>Hello</b>',
                '&lt;b&gt;Hello&lt;/b&gt;'
            ],
            'quotes and apostrophes' => [
                '"Hello" \'World\'',
                '&quot;Hello&quot; &#039;World&#039;'
            ],
            'ampersands' => [
                'Tom & Jerry',
                'Tom &amp; Jerry',
            ],
            'utf8 accented' => [
                'Café',
                'Caf&eacute;',
            ],
        ];
    }
    
    // -------------------------------------------------
    // Double-encode toggle
    // -------------------------------------------------
    
    public function testDoubleEncodeFalseIsIdempotent(): void
    {
        $transformer = new HtmlEntitiesTransformer(doubleEncode: false);
        
        $input = '<b>';
        $once = $transformer->apply($input, $this->field, $this->context);
        $twice = $transformer->apply($once, $this->field, $this->context);
        
        $this->assertSame($once, $twice, 'doubleEncode=false must yield idempotent output');
    }
    
    public function testDoubleEncodeTrueEncodesEntitiesAgain(): void
    {
        $transformer = new HtmlEntitiesTransformer(doubleEncode: true);
        
        $input = '<b>';
        $once = $transformer->apply($input, $this->field, $this->context);      // &lt;b&gt;
        $twice = $transformer->apply($once, $this->field, $this->context);      // should encode & and ; again
        
        $this->assertNotSame($once, $twice, 'doubleEncode=true must re-escape entities');
        $this->assertStringContainsString('&amp;lt;', $twice);
    }
    
    // -------------------------------------------------
    // Invalid UTF-8 handling
    // -------------------------------------------------
    
    public function testInvalidUtf8IsSubstituted(): void
    {
        $transformer = new HtmlEntitiesTransformer();
        
        $input = "\xF0\x28\x8C\x28"; // invalid UTF-8
        $output = $transformer->apply($input, $this->field, $this->context);
        
        $this->assertNotSame($input, $output, 'Invalid UTF-8 must not survive unchanged');
        $this->assertIsString($output);
        $this->assertStringContainsString('�', $output, 'Expected replacement character (U+FFFD)');
    }
    
    // -------------------------------------------------
    // Type safety
    // -------------------------------------------------
    
    public function testNonStringValuesAreUntouched(): void
    {
        $transformer = new HtmlEntitiesTransformer();
        
        $this->assertSame(42, $transformer->apply(42, $this->field, $this->context));
        $this->assertSame(['<b>'], $transformer->apply(['<b>'], $this->field, $this->context));
        $this->assertNull($transformer->apply(null, $this->field, $this->context));
    }
    
    public function testEmptyStringIsUntouched(): void
    {
        $transformer = new HtmlEntitiesTransformer();
        $this->assertSame('', $transformer->apply('', $this->field, $this->context));
    }
}
