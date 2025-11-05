<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\SanitizeTextFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\SanitizeTextFilter
 */
final class SanitizeTextFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->field = new FieldDefinition('comment');
    }
    
    // -------------------------------------------------
    // 🔹 Core cases
    // -------------------------------------------------
    
    #[DataProvider('provideTextSamples')]
    public function testSanitizesVariousTextCases(string $input, string $expected): void
    {
        $filter = new SanitizeTextFilter(); // strict mode ON by default
        $output = $filter->apply($input, $this->field);
        
        $this->assertSame($expected, $output);
    }
    
    public static function provideTextSamples(): array
    {
        return [
            'valid ascii text' => ['Hello World!', 'Hello World!'],
            'unicode text untouched' => ['Café déjà vu', 'Café déjà vu'],
            'emoji preserved (valid UTF-8)' => ['Nice 👍 day!', 'Nice 👍 day!'],
            'invalid utf8 sequence removed (strict removes replacement)' => [
                "Valid©Invalid�",
                "Valid©Invalid",
            ],
            'control characters removed' => ["Hello\x01World\x0E", 'HelloWorld'],
            'line breaks and tabs preserved' => ["Line1\nLine2\tTabbed", "Line1\nLine2\tTabbed"],
            'trims trailing whitespace' => [" Hello World \n\n", 'Hello World'],
            'multiple spaces kept (middle)' => ['Hello   World', 'Hello   World'],
            'mix of invalid + control + whitespace' => [
                "Bad\xC3\x28Stuff\x01\tDone ",
                "Bad(Stuff\tDone",
            ],
        ];
    }
    
    // -------------------------------------------------
    // 🔹 Non-string cases
    // -------------------------------------------------
    
    #[DataProvider('provideNonStringValues')]
    public function testNonStringValuesAreReturnedUnchanged(mixed $input): void
    {
        $filter = new SanitizeTextFilter();
        $output = $filter->apply($input, $this->field);
        
        $this->assertSame($input, $output);
    }
    
    public static function provideNonStringValues(): array
    {
        return [
            'null' => [null],
            'int' => [42],
            'array' => [['text' => 'ok']],
            'object' => [(object)['a' => 1]],
        ];
    }
    
    // -------------------------------------------------
    // 🔹 Relaxed mode (preserves replacement char)
    // -------------------------------------------------
    
    #[DataProvider('provideRelaxedModeSamples')]
    public function testRelaxedModeKeepsReplacementChar(string $input, string $expected): void
    {
        $filter = new SanitizeTextFilter(removeReplacementChar: false);
        $output = $filter->apply($input, $this->field);
        
        $this->assertSame($expected, $output);
    }
    
    public static function provideRelaxedModeSamples(): array
    {
        return [
            'keeps U+FFFD replacement' => [
                "Valid©Invalid�",
                "Valid©Invalid�",
            ],
            'normal text unchanged' => [
                "Hello world",
                "Hello world",
            ],
        ];
    }
    
    // -------------------------------------------------
    // 🔹 Robustness
    // -------------------------------------------------
    
    public function testRemovesBrokenUtf8Safely(): void
    {
        $filter = new SanitizeTextFilter();
        $input = "Broken\xC3 string"; // invalid UTF-8 byte
        $output = $filter->apply($input, $this->field);
        
        $this->assertSame('Broken string', $output);
    }
}
