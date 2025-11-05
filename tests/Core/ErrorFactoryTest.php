<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\ErrorFactory;
use FormToEmail\Core\FieldDefinition;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Core\ErrorFactory
 */
final class ErrorFactoryTest extends TestCase
{
    private FieldDefinition $field;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->field = new FieldDefinition('username');
    }
    
    public function testNormalizeFromStringCode(): void
    {
        $error = ErrorFactory::normalize('required', $this->field);
        
        $this->assertInstanceOf(ErrorDefinition::class, $error);
        $this->assertSame('required', $error->getCode());
        $this->assertSame('Required', $error->getMessage());
        $this->assertSame('username', $error->getField());
        $this->assertSame([], $error->getContext());
    }
    
    public function testNormalizeFromErrorDefinitionReturnsSameInstance(): void
    {
        $existing = new ErrorDefinition(
            code: 'invalid_format',
            message: 'Invalid format',
            field: 'email'
        );
        
        $result = ErrorFactory::normalize($existing, $this->field);
        $this->assertSame($existing, $result);
    }
    
    public function testNormalizeFromArrayCreatesErrorDefinition(): void
    {
        $error = ErrorFactory::normalize(
            [
                'code' => 'too_short',
                'message' => 'Minimum {min} characters required',
                'context' => ['min' => 5],
            ],
            $this->field
        );
        
        $this->assertInstanceOf(ErrorDefinition::class, $error);
        $this->assertSame('too_short', $error->getCode());
        $this->assertSame('Minimum {min} characters required', $error->getMessage());
        $this->assertSame(['min' => 5], $error->getContext());
        $this->assertSame('username', $error->getField());
    }
    
    public function testNormalizeThrowsOnInvalidType(): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line */
        ErrorFactory::normalize(123, $this->field);
    }
    
    public function testAutoMessageConvertsSnakeToHumanReadable(): void
    {
        $this->assertSame('Too short', ErrorFactory::autoMessage('too_short'));
        $this->assertSame('Invalid email', ErrorFactory::autoMessage('invalid_email'));
    }
}
