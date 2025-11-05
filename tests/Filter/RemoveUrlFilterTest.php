<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\RemoveUrlFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\RemoveUrlFilter
 *
 * Ensures URLs, disguised links, and domains are removed or replaced safely.
 */
final class RemoveUrlFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('message');
    }
    
    // --------------------------------------------------------
    // DEFAULT (AGGRESSIVE REMOVAL MODE)
    // --------------------------------------------------------
    
    #[DataProvider('provideUrlRemovals')]
    public function testRemovesUrls(string $input, string $expected): void
    {
        $filter = new RemoveUrlFilter();
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public static function provideUrlRemovals(): array
    {
        return [
            'plain http' => ['Visit http://example.com for info', 'Visit for info'],
            'https secure link' => ['Check https://secure.site/login', 'Check'],
            'www domain' => ['Go to www.example.org now', 'Go to now'],
            'bare domain' => ['Contact us at example.com', 'Contact us at'],
            'email left intact' => ['Email me at john@example.com', 'Email me at john@example.com'],
            'query params' => ['Try https://example.com/search?q=hello', 'Try'],
            'multiple links' => ['See http://a.com and https://b.org', 'See and'],
            'obfuscated [.] domain' => ['visit example[.]com now', 'visit now'],
            'obfuscated dot text' => ['check example dot com', 'check'],
            'hxxp spam style' => ['hxxp://malicious.site/path', ''],
            'unicode dots' => ['weird Ｅxample․com format', 'weird format'],
        ];
    }
    
    // --------------------------------------------------------
    // PLACEHOLDER MODE
    // --------------------------------------------------------
    
    public function testReplacesUrlsWithPlaceholder(): void
    {
        $filter = new RemoveUrlFilter(replaceWithPlaceholder: true);
        $input = 'Visit http://example.com and www.site.org';
        $expected = 'Visit [link removed] and [link removed]';
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    public function testCustomPlaceholderWorks(): void
    {
        $filter = new RemoveUrlFilter(true, '[URL]');
        $input = 'Go to https://test.io or test.com';
        $expected = 'Go to [URL] or [URL]';
        $this->assertSame($expected, $filter->apply($input, $this->field));
    }
    
    // --------------------------------------------------------
    // NON-STRING INPUTS
    // --------------------------------------------------------
    
    #[DataProvider('provideNonStrings')]
    public function testNonStringValuesAreUntouched(mixed $input): void
    {
        $filter = new RemoveUrlFilter();
        $this->assertSame($input, $filter->apply($input, $this->field));
    }
    
    public static function provideNonStrings(): array
    {
        return [
            'null' => [null],
            'int' => [42],
            'bool' => [false],
            'array' => [['https://example.com']],
        ];
    }
}
