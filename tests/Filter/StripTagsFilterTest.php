<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\StripTagsFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\StripTagsFilter
 */
final class StripTagsFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->field = new FieldDefinition('message');
    }
    
    // -------------------------------------------------
    // 🔹 Main behavior
    // -------------------------------------------------
    
    #[DataProvider('provideHtmlSamples')]
    public function testStripsTagsCorrectly(array $allowed, string $input, string $expected): void
    {
        $filter = new StripTagsFilter($allowed);
        $output = $filter->apply($input, $this->field);
        
        $this->assertSame($expected, $output);
    }
    
    public static function provideHtmlSamples(): array
    {
        return [
            'basic html removal' => [
                [],
                '<p>Hello <b>World</b></p>',
                'Hello World',
            ],
            'allow bold' => [
                ['<b>'],
                '<p>Hello <b>World</b></p>',
                'Hello <b>World</b>',
            ],
            'allow italics and bold' => [
                ['<b>', '<i>'],
                '<i>Nice</i> <u>and</u> <b>bold</b>',
                '<i>Nice</i> and <b>bold</b>',
            ],
            'strip script tags' => [
                [],
                'Click <script>alert("hack");</script>here',
                'Click here',
            ],
            'strip mixed html and text' => [
                [],
                'Safe <em>text</em> with <a href="#">links</a>',
                'Safe text with links',
            ],
            'nested tags' => [
                ['<b>'],
                '<b><i>Deep</i> inside</b>',
                '<b>Deep inside</b>',
            ],
            'malformed tags' => [
                [],
                '<b><i>Broken',
                'Broken',
            ],
            'empty string' => [
                [],
                '',
                '',
            ],
            'html entities remain' => [
                [],
                '&lt;b&gt;not real tag&lt;/b&gt;',
                '&lt;b&gt;not real tag&lt;/b&gt;',
            ],
        ];
    }
    
    // -------------------------------------------------
    // 🔹 Edge cases
    // -------------------------------------------------
    
    #[DataProvider('provideNonStringValues')]
    public function testNonStringValuesAreReturnedUnchanged(mixed $value): void
    {
        $filter = new StripTagsFilter();
        $output = $filter->apply($value, $this->field);
        
        $this->assertSame($value, $output);
    }
    
    public static function provideNonStringValues(): array
    {
        return [
            'integer' => [42],
            'null' => [null],
            'array' => [['<b>hey</b>']],
            'object' => [(object)['x' => '<b>test</b>']],
        ];
    }
    
    // -------------------------------------------------
    // 🔹 Constructor configuration
    // -------------------------------------------------
    
    public function testAllowedTagsAreRespected(): void
    {
        $filter = new StripTagsFilter(['<i>']);
        $input = '<p>Hello <i>world</i></p>';
        $this->assertSame('Hello <i>world</i>', $filter->apply($input, $this->field));
    }
}
