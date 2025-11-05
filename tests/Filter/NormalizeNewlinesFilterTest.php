<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\NormalizeNewlinesFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\NormalizeNewlinesFilter
 *
 * Ensures consistent newline normalization with and without
 * enforced trailing newline behavior.
 */
final class NormalizeNewlinesFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('message');
    }
    
    // --------------------------------------------------------
    // TRAILING NEWLINE ENABLED
    // --------------------------------------------------------
    
    #[DataProvider('provideWithTrailingNewline')]
    public function testNormalizesWithTrailingNewline(string $input, string $expected): void
    {
        $filter = new NormalizeNewlinesFilter(ensureTrailingNewline: true);
        $output = $filter->apply($input, $this->field);
        $this->assertSame($expected, $output);
    }
    
    public static function provideWithTrailingNewline(): array
    {
        return [
            'LF unchanged' => ["Line1\nLine2\n", "Line1\nLine2\n"],
            'CRLF converted' => ["A\r\nB\r\n", "A\nB\n"],
            'CR converted' => ["A\rB\r", "A\nB\n"],
            'mixed endings' => ["A\r\nB\rC\n", "A\nB\nC\n"],
            'no final newline gets one' => ["Hello", "Hello\n"],
            'multiple trailing collapsed' => ["Hi\n\n\n", "Hi\n"],
        ];
    }
    
    // --------------------------------------------------------
    // TRAILING NEWLINE DISABLED
    // --------------------------------------------------------
    
    #[DataProvider('provideWithoutTrailingNewline')]
    public function testNormalizesWithoutTrailingNewline(string $input, string $expected): void
    {
        $filter = new NormalizeNewlinesFilter(ensureTrailingNewline: false);
        $output = $filter->apply($input, $this->field);
        $this->assertSame($expected, $output);
    }
    
    public static function provideWithoutTrailingNewline(): array
    {
        return [
            'LF unchanged' => ["Line1\nLine2\n", "Line1\nLine2\n"],
            'CRLF converted' => ["A\r\nB\r\n", "A\nB\n"],
            'CR converted' => ["A\rB\r", "A\nB\n"],
            'mixed endings' => ["A\r\nB\rC\n", "A\nB\nC\n"],
            'no final newline preserved' => ["Hello", "Hello"],
            'multiple trailing collapsed but no extra added' => ["Hi\n\n\n", "Hi\n"],
        ];
    }
    
    // --------------------------------------------------------
    // NON-STRING INPUTS
    // --------------------------------------------------------
    
    #[DataProvider('provideNonStrings')]
    public function testNonStringValuesAreUntouched(mixed $input): void
    {
        $filter = new NormalizeNewlinesFilter();
        $output = $filter->apply($input, $this->field);
        $this->assertSame($input, $output);
    }
    
    public static function provideNonStrings(): array
    {
        return [
            'null' => [null],
            'int' => [42],
            'float' => [3.14],
            'bool' => [true],
            'array' => [["Line1\r\nLine2"]],
        ];
    }
    
    // --------------------------------------------------------
    // FUZZ TEST
    // --------------------------------------------------------
    
    public function testRandomMixedNewlinesAlwaysConvertToLF(): void
    {
        $filter = new NormalizeNewlinesFilter();
        
        $patterns = ["\r", "\r\n", "\n"];
        for ($i = 0; $i < 50; $i++) {
            $input = '';
            for ($j = 0; $j < 5; $j++) {
                $input .= "Line{$j}" . $patterns[array_rand($patterns)];
            }
            
            $output = $filter->apply($input, $this->field);
            $this->assertStringNotContainsString("\r", $output);
            $this->assertMatchesRegularExpression('/^(?:[^\r]*\n)+$/', $output);
        }
    }
}
