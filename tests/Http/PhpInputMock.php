<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Http;

/**
 * Mock stream wrapper for php://input.
 *
 * PHPUnit cannot easily feed data into php://input, so this
 * replaces it temporarily for the duration of a test.
 */
final class PhpInputMock
{
    public static ?string $mockInput = null;
    private $handle;
    
    public function stream_open(string $path, string $mode): bool
    {
        $this->handle = fopen(self::$mockInput, 'r');
        return $this->handle !== false;
    }
    
    public function stream_read(int $count): string|false
    {
        return fread($this->handle, $count);
    }
    
    public function stream_eof(): bool
    {
        return feof($this->handle);
    }
    
    public function stream_close(): void
    {
        fclose($this->handle);
    }
}
