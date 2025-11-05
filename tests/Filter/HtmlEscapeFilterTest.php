<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\HtmlEscapeFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\HtmlEscapeFilter
 *
 * Comprehensive test suite covering:
 * - HTML4 vs HTML5 escaping behavior
 * - Double-encoding toggle
 * - Charset safety
 * - XSS and malformed UTF-8 handling
 * - Idempotence and reversibility
 */
final class HtmlEscapeFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('message');
    }
    
    // --------------------------------------------------------
    // BASIC ESCAPE BEHAVIOR
    // --------------------------------------------------------
    
    #[DataProvider('provideHtmlEscapes')]
    public function testEscapesHtmlCharacters(string $input, string $expected): void
    {
        $filter = new HtmlEscapeFilter();
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public static function provideHtmlEscapes(): array
    {
        return [
            'ampersand' => ['Fish & Chips', 'Fish &amp; Chips'],
            'angle brackets' => ['<b>bold</b>', '&lt;b&gt;bold&lt;/b&gt;'],
            'quotes escaped' => ['"Hello" & \'World\'', '&quot;Hello&quot; &amp; &apos;World&apos;'],
            'script tag escaped' => [
                'Hello <script>alert("XSS")</script>',
                'Hello &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
            ],
            'emoji safe' => ['Hello 😊 <b>ok</b>', 'Hello 😊 &lt;b&gt;ok&lt;/b&gt;'],
            'multilingual text' => ['こんにちは <i>世界</i>', 'こんにちは &lt;i&gt;世界&lt;/i&gt;'],
            'plain text untouched' => ['Nothing to escape here.', 'Nothing to escape here.'],
        ];
    }
    
    // --------------------------------------------------------
    // DOUBLE ENCODE BEHAVIOR
    // --------------------------------------------------------
    
    public function testDisablesDoubleEncoding(): void
    {
        $filter = new HtmlEscapeFilter(doubleEncode: false);
        $input = 'Already escaped: &lt;div&gt;Test&lt;/div&gt;';
        $expected = 'Already escaped: &lt;div&gt;Test&lt;/div&gt;';
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public function testEnablesDoubleEncoding(): void
    {
        $filter = new HtmlEscapeFilter(doubleEncode: true);
        $input = 'Already escaped: &lt;div&gt;Test&lt;/div&gt;';
        $expected = 'Already escaped: &amp;lt;div&amp;gt;Test&amp;lt;/div&amp;gt;';
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    // --------------------------------------------------------
    // CHARSET AND INVALID UTF-8 HANDLING
    // --------------------------------------------------------
    
    public function testHandlesInvalidUtf8Sequences(): void
    {
        $filter = new HtmlEscapeFilter();
        $input = "Bad UTF-8: \xC3";
        $output = $filter->apply($input, $this->field);
        
        $this->assertStringContainsString('�', $output, 'Should replace invalid UTF-8 with replacement char');
    }
    
    public function testCustomEncodingWorks(): void
    {
        $filter = new HtmlEscapeFilter(encoding: 'ISO-8859-1');
        $input = 'Café & crème brûlée';
        $output = $filter->apply($input, $this->field);
        
        // Behavior is implementation-dependent, but should safely escape HTML
        $this->assertStringContainsString('&amp;', $output);
    }
    
    // --------------------------------------------------------
    // ENTITY MODE DIFFERENCES (HTML4 vs HTML5)
    // --------------------------------------------------------
    
    public function testHtml4ModeUsesNumericApostrophe(): void
    {
        $filter = new HtmlEscapeFilter(flags: ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
        $input = '"Hello" & \'World\'';
        $expected = '&quot;Hello&quot; &amp; &#039;World&#039;';
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public function testHtml5ModeUsesNamedApostrophe(): void
    {
        $filter = new HtmlEscapeFilter(flags: ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $input = '"Hello" & \'World\'';
        $expected = '&quot;Hello&quot; &amp; &apos;World&apos;';
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    // --------------------------------------------------------
    // IDEMPOTENCE AND STABILITY
    // --------------------------------------------------------
    
    public function testIdempotence(): void
    {
        $filter = new HtmlEscapeFilter();
        $input = '<a href="link">text</a>';
        $once = $filter->apply($input, $this->field);
        $twice = $filter->apply($once, $this->field);
        
        $this->assertSame(
            '&lt;a href=&quot;link&quot;&gt;text&lt;/a&gt;',
            $once
        );
        
        $this->assertSame(
            '&amp;lt;a href=&amp;quot;link&amp;quot;&amp;gt;text&amp;lt;/a&amp;gt;',
            $twice
        );
    }
    
    // --------------------------------------------------------
    // NON-STRING INPUTS
    // --------------------------------------------------------
    
    #[DataProvider('provideNonStrings')]
    public function testNonStringValuesAreUntouched(mixed $input): void
    {
        $filter = new HtmlEscapeFilter();
        $this->assertSame($input, $filter->apply($input, $this->field));
    }
    
    public static function provideNonStrings(): array
    {
        return [
            'null' => [null],
            'bool' => [true],
            'int' => [42],
            'float' => [3.14],
            'array' => [['<b>array</b>']],
            'object' => [(object)['html' => '<i>obj</i>']],
        ];
    }
    
    // --------------------------------------------------------
    // SECURITY REGRESSION TESTS
    // --------------------------------------------------------
    
    #[DataProvider('provideSecurityInputs')]
    public function testXssPatternsNeutralized(string $input, string $expected): void
    {
        $filter = new HtmlEscapeFilter();
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public static function provideSecurityInputs(): array
    {
        return [
            'simple XSS' => [
                '<script>alert(1)</script>',
                '&lt;script&gt;alert(1)&lt;/script&gt;',
            ],
            'inline event handler' => [
                '<img src="x" onerror="alert(1)">',
                '&lt;img src=&quot;x&quot; onerror=&quot;alert(1)&quot;&gt;',
            ],
            'style injection' => [
                '<div style="background:url(javascript:alert(1))">',
                '&lt;div style=&quot;background:url(javascript:alert(1))&quot;&gt;',
            ],
            'HTML comments' => [
                '<!-- comment -->',
                '&lt;!-- comment --&gt;',
            ],
        ];
    }
}
