<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\RemoveEmojiFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\RemoveEmojiFilter
 *
 * Ensures emoji and pictographic symbols are reliably removed,
 * while preserving valid Unicode letters, numbers, punctuation,
 * and structure of normal text.
 */
final class RemoveEmojiFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('message');
    }
    
    // --------------------------------------------------------
    // MAIN FUNCTIONAL TESTS
    // --------------------------------------------------------
    
    #[DataProvider('provideEmojiCases')]
    public function testRemovesEmojisCorrectly(string $input, string $expected): void
    {
        $filter = new RemoveEmojiFilter();
        $output = $filter->apply($input, $this->field);
        $this->assertSame($expected, $output);
    }
    
    public static function provideEmojiCases(): array
    {
        return [
            'plain text unchanged' => ['Hello World', 'Hello World'],
            'single emoji removed' => ['Hello 😊', 'Hello'],
            'emoji in middle removed' => ['Hi 👋 there', 'Hi there'],
            'multiple emojis removed' => ['🔥🚀💻⚡', ''],
            'mixed symbols and emojis' => ['Cool 😎 text ✨ with ❤️ and ☀️', 'Cool text with and'],
            'emoji + punctuation spacing preserved' => ['Great job! 👏👏👏', 'Great job!'],
            'emoji with skin tone modifier' => ['👍🏽 OK', 'OK'],
            'emoji + flags' => ['🇨🇦🇫🇷 Hello', 'Hello'],
            'emoji inside sentence' => ['This 🧠 test ✅ works!', 'This test works!'],
            'extra whitespace trimmed' => ['Hi ✈️  ', 'Hi'],
        ];
    }
    
    // --------------------------------------------------------
    // NON-STRING INPUTS
    // --------------------------------------------------------
    
    #[DataProvider('provideNonStrings')]
    public function testNonStringValuesAreUntouched(mixed $input): void
    {
        $filter = new RemoveEmojiFilter();
        $output = $filter->apply($input, $this->field);
        $this->assertSame($input, $output);
    }
    
    public static function provideNonStrings(): array
    {
        return [
            'null' => [null],
            'bool' => [false],
            'int' => [42],
            'float' => [3.14],
            'array' => [['😊']],
            'object' => [(object)['msg' => '🚀']],
        ];
    }
    
    // --------------------------------------------------------
    // COMPLEX EDGE CASES
    // --------------------------------------------------------
    
    #[DataProvider('provideComplexCases')]
    public function testComplexEmojiRemoval(string $input, string $expected): void
    {
        $filter = new RemoveEmojiFilter();
        $output = $filter->apply($input, $this->field);
        $this->assertSame($expected, $output);
    }
    
    public static function provideComplexCases(): array
    {
        return [
            'Zalgo text preserved' => ["T͜͡e͠x̷t̛ 😈", "T͜͡e͠x̷t̛"],
            'Dingbats removed' => ['☕ Coffee Time ☂️', 'Coffee Time'],
            'Transport symbols removed' => ['🚗 Car → 🛫 Plane', 'Car → Plane'],
            'Misc pictographs removed' => ['⚽🏀🏈🎾🏐', ''],
            'Supplemental symbols removed' => ['🪐🌌✨', ''],
            'Multiple categories mixed' => ['☀️🏝️🍹Summer Vibes', 'Summer Vibes'],
        ];
    }
    
    // --------------------------------------------------------
    // FUZZ SANITY TEST (RANDOM ROBUSTNESS)
    // --------------------------------------------------------
    
    public function testFuzzRandomUnicodeInputRemainsValid(): void
    {
        $filter = new RemoveEmojiFilter();
        mt_srand(84);
        
        for ($i = 0; $i < 150; $i++) {
            $input = $this->generateRandomUnicodeString();
            $output = $filter->apply($input, $this->field);
            
            $this->assertIsString($output);
            // Ensure no emoji blocks remain
            $this->assertMatchesRegularExpression(
                '/^[^\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]*$/u',
                $output,
                "Unexpected emoji survived: {$output}"
            );
        }
    }
    
    private function generateRandomUnicodeString(): string
    {
        $ranges = [
            [0x0041, 0x007A], // Latin
            [0x0400, 0x045F], // Cyrillic
            [0x0600, 0x06FF], // Arabic
            [0x0900, 0x097F], // Devanagari
            [0x1F300, 0x1F6FF], // Emoji
        ];
        
        $len = random_int(5, 30);
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $r = $ranges[array_rand($ranges)];
            $code = random_int($r[0], $r[1]);
            $str .= mb_chr($code, 'UTF-8');
        }
        return $str;
    }
}
