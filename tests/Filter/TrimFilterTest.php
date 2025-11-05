<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\TrimFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\TrimFilter
 */
final class TrimFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->field = new FieldDefinition('name');
    }
    
    #[DataProvider('provideUnicodeAwareCases')]
    public function testUnicodeAwareTrimming(string $input, string $expected): void
    {
        $filter = new TrimFilter(unicodeAware: true);
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public static function provideUnicodeAwareCases(): array
    {
        return [
            'ascii spaces' => ['  Julien  ', 'Julien'],
            'tabs and newlines' => ["\n\t  Julien \t\n", 'Julien'],
            'full-width unicode' => ["\u{3000}Julien\u{3000}", 'Julien'],
            'internal space preserved' => ['  Julien Turbide  ', 'Julien Turbide'],
        ];
    }
    
    #[DataProvider('provideLegacyCases')]
    public function testLegacyTrimDoesNotAffectUnicode(string $input, string $expected): void
    {
        $filter = new TrimFilter(unicodeAware: false);
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public static function provideLegacyCases(): array
    {
        return [
            'ascii spaces' => ['  Julien  ', 'Julien'],
            'unicode untouched' => ["\u{3000}Julien\u{3000}", "\u{3000}Julien\u{3000}"],
        ];
    }
    
    #[DataProvider('provideDirectionalCases')]
    public function testDirectionalTrimming(string $mode, string $input, string $expected): void
    {
        $filter = new TrimFilter(unicodeAware: true, mode: $mode);
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public static function provideDirectionalCases(): array
    {
        return [
            'left only' => ['left', '   Julien  ', 'Julien  '],
            'right only' => ['right', '   Julien  ', '   Julien'],
            'both sides' => ['both', '   Julien  ', 'Julien'],
        ];
    }
    
    public function testInvalidModeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TrimFilter(unicodeAware: true, mode: 'invalid');
    }
    
    public function testNonStringsAreUntouched(): void
    {
        $filter = new TrimFilter();
        $this->assertNull($filter->apply(null, $this->field));
        $this->assertSame(42, $filter->apply(42, $this->field));
        $this->assertSame(['x'], $filter->apply(['x'], $this->field));
        $this->assertSame(false, $filter->apply(false, $this->field));
    }
    
    public function testIdempotency(): void
    {
        $filter = new TrimFilter();
        $input = "  Julien  ";
        $once = $filter->apply($input, $this->field);
        $twice = $filter->apply($once, $this->field);
        $this->assertSame($once, $twice, 'Filter should be idempotent');
    }
}
