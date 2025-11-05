<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Filter;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Filter\SanitizeEmailFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Filter\SanitizeEmailFilter
 *
 * Comprehensive test suite for the email sanitizer.
 * Validates RFC-5321 strict ASCII and RFC-6531 relaxed Unicode behavior,
 * along with safety guarantees against injection, emojis, and malformed data.
 */
final class SanitizeEmailFilterTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        $this->field = new FieldDefinition('email');
    }
    
    // --------------------------------------------------------
    // STRICT MODE (RFC 5321-safe, ASCII only)
    // --------------------------------------------------------
    
    #[DataProvider('provideStrictEmails')]
    public function testSanitizesStrictly(string $input, string $expected): void
    {
        $filter = new SanitizeEmailFilter(strict: true);
        $output = $filter->apply($input, $this->field);
        $this->assertSame($expected, $output);
    }
    
    public static function provideStrictEmails(): array
    {
        return [
            // ✅ Basic normalization
            'ASCII untouched' => ['user@example.com', 'user@example.com'],
            'trims spaces' => ['  User@Example.COM  ', 'User@example.com'],
            'leading/trailing dots removed' => ['.foo.bar.@example.com', 'foo.bar@example.com'],
            'multiple dots collapsed' => ['foo..bar@example.com', 'foo.bar@example.com'],
            'double at sign reduced' => ['foo@@example.com', 'foo@example.com'],
            
            // ✅ Security / header injection prevention
            'newline sanitized' => ["user@example.com\nCC:evil@hack.com", 'user@example.comcc:evilhack.com'],
            'header injection removed' => ["evil@example.com\r\nBcc:admin@hack.com", 'evil@example.combcc:adminhack.com'],
            'to header removed' => ["victim@example.com\r\nTo:root@evil.com", 'victim@example.comto:rootevil.com'],
            'subject injection neutralized' => ["joe@example.com\r\nSubject:Hacked", 'joe@example.comsubject:hacked'],
            'encoded CRLF injection' => ["user@example.com%0ACc:evil@hack.com", 'user@example.comcc:evilhack.com'],
            'angle brackets removed' => ['<user@example.com>', 'user@example.com'],
            
            // ✅ Emoji, accents, control chars
            'emoji stripped' => ['john👍@example.com', 'john@example.com'],
            'accented stripped' => ['tést@exámple.com', 'tst@xn--exmple-qta.com'],
            'Unicode local stripped' => ['jöhn@bücher.de', 'jhn@xn--bcher-kva.de'],
            'weird invisible chars removed' => ["user\u{200B}@example.com", 'user@example.com'],
            'RTL override removed' => ["\u{202E}root@example.com", 'root@example.com'],
            'control chars removed' => ["\x01\x02test@example.com\x7F", 'test@example.com'],
            
            // ✅ IDN handling
            'IDN domain normalized' => ['user@bücher.de', 'user@xn--bcher-kva.de'],
            'accented domain normalized' => ['user@exámple.com', 'user@xn--exmple-qta.com'],
            
            // ✅ Mixed malformed inputs
            'space in local removed' => ['user name@example.com', 'username@example.com'],
            'invalid symbols removed' => ['jo(e)!hn@example.com', 'joe!hn@example.com'],
            'very long input truncated logically' => [
                str_repeat('a', 200) . '@example.com',
                str_repeat('a', 200) . '@example.com'
            ],
            
            // ✅ Additional edge cases
            'Unicode + tag + IDN' => ['MýName+Tag@dömäin.fr', 'MName+Tag@xn--dmin-moa0i.fr'],
            'subdomain preserved' => ['user@sub.bücher.de', 'user@sub.xn--bcher-kva.de'],
            'apostrophe and hyphen' => ["o'hara-smith@exámple.com", "o'hara-smith@xn--exmple-qta.com"],
            'double dots + accents' => ['héllo..wörld@exámple.com', 'hllo.wrld@xn--exmple-qta.com'],
            'already punycode' => ['user@xn--bcher-kva.de', 'user@xn--bcher-kva.de'],
        ];
    }
    
    // --------------------------------------------------------
    // RELAXED MODE (RFC 6531, Unicode local-part allowed)
    // --------------------------------------------------------
    
    #[DataProvider('provideRelaxedEmails')]
    public function testRelaxedModePreservesUnicode(string $input, string $expected): void
    {
        $filter = new SanitizeEmailFilter(strict: false);
        $output = $filter->apply($input, $this->field);
        $this->assertSame($expected, $output);
    }
    
    public static function provideRelaxedEmails(): array
    {
        return [
            // ✅ Unicode preservation
            'accent preserved' => ['tést@exámple.com', 'tést@xn--exmple-qta.com'],
            'emoji stripped but rest intact' => ['joe👍@bücher.de', 'joe@xn--bcher-kva.de'],
            'IDN converted but Unicode local kept' => ['jöhn@bücher.de', 'jöhn@xn--bcher-kva.de'],
            'zero-width spaces removed' => ["user\u{200B}@example.com", 'user@example.com'],
            'RTL mark removed' => ["\u{200F}admin@bücher.de", 'admin@xn--bcher-kva.de'],
            
            // ✅ Mixed Unicode cases
            'multi-accent name' => ['héllö.wörld@exámple.com', 'héllö.wörld@xn--exmple-qta.com'],
            'tagged local' => ['hello+tag@exámple.com', 'hello+tag@xn--exmple-qta.com'],
            'underscore preserved' => ['héllo_world@exámple.com', 'héllo_world@xn--exmple-qta.com'],
            'hyphen preserved' => ['héllo-world@exámple.com', 'héllo-world@xn--exmple-qta.com'],
            'multiple @ signs collapsed' => ['foo@@@bücher.de', 'foo@xn--bcher-kva.de'],
            
            // ✅ Legitimate symbol retention
            'plus preserved' => ['user+newsletter@bücher.de', 'user+newsletter@xn--bcher-kva.de'],
            'apostrophe preserved' => ["o'hara@bücher.de", "o'hara@xn--bcher-kva.de"],
            
            // ✅ Additional new edge cases mirrored in relaxed mode
            'Unicode + tag + IDN' => ['MýName+Tag@dömäin.fr', 'MýName+Tag@xn--dmin-moa0i.fr'],
            'subdomain preserved' => ['user@sub.bücher.de', 'user@sub.xn--bcher-kva.de'],
            'apostrophe and hyphen' => ["o'hara-smith@exámple.com", "o'hara-smith@xn--exmple-qta.com"],
            'double dots + accents' => ['héllo..wörld@exámple.com', 'héllo.wörld@xn--exmple-qta.com'],
            'already punycode' => ['user@xn--bcher-kva.de', 'user@xn--bcher-kva.de'],
        ];
    }
    
    // --------------------------------------------------------
    // MALFORMED INPUTS
    // --------------------------------------------------------
    
    #[DataProvider('provideMalformedEmails')]
    public function testHandlesMalformedInputsGracefully(string $input): void
    {
        $filter = new SanitizeEmailFilter(strict: true);
        $output = $filter->apply($input, $this->field);
        
        $this->assertIsString($output);
        $this->assertStringNotContainsString("\r", $output);
        $this->assertStringNotContainsString("\n", $output);
        $this->assertLessThanOrEqual(254, strlen($output));
    }
    
    public static function provideMalformedEmails(): array
    {
        return [
            'no at sign' => ['username'],
            'missing domain' => ['user@'],
            'only at sign' => ['@'],
            'binary junk' => ["\x00\xffuser@example.com"],
            'emoji only' => ['👍👍👍'],
            'html tag' => ['<b>user</b>@example.com'],
            'script injection' => ['<script>alert(1)</script>@example.com'],
        ];
    }
    
    // --------------------------------------------------------
    // NON-STRING INPUTS
    // --------------------------------------------------------
    
    #[DataProvider('provideNonStrings')]
    public function testNonStringValuesAreUntouched(mixed $input): void
    {
        $filter = new SanitizeEmailFilter();
        $output = $filter->apply($input, $this->field);
        $this->assertSame($input, $output);
    }
    
    public static function provideNonStrings(): array
    {
        return [
            'null' => [null],
            'bool' => [true],
            'int' => [42],
            'float' => [42.5],
            'array' => [['email' => 'a@b.com']],
            'object' => [(object)['email' => 'x@y.com']],
        ];
    }
    
    // --------------------------------------------------------
    // FUZZ SANITY TEST (random robustness)
    // --------------------------------------------------------
    
    public function testFuzzRandomInputsAlwaysSafe(): void
    {
        $filter = new SanitizeEmailFilter(strict: true);
        mt_srand(42); // reproducible seed
        
        for ($i = 0; $i < 200; $i++) {
            $random = $this->randomGarbageEmail();
            $result = $filter->apply($random, $this->field);
            
            $this->assertIsString($result);
            $this->assertStringNotContainsString("\r", $result);
            $this->assertStringNotContainsString("\n", $result);
            $this->assertLessThanOrEqual(254, strlen($result));
            $this->assertTrue(substr_count($result, '@') <= 1, "Too many @ in result: {$result}");
        }
    }
    
    private function randomGarbageEmail(): string
    {
        $chars = array_merge(
            range('a', 'z'),
            range('A', 'Z'),
            range('0', '9'),
            str_split("!#$%&'*+./=?^_`{|}~@ \t\r\n😀💩éü<>()[]{}")
        );
        $len = random_int(5, 40);
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[array_rand($chars)];
        }
        return $out;
    }
}
