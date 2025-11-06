<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;
use FormToEmail\Core\FormContext;
use FormToEmail\Enum\FieldRole;
use FormToEmail\Filter\Filter;
use FormToEmail\Rule\Rule;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FieldDefinition.
 *
 * Ensures field configuration behaves predictably:
 *  - correct initialization
 *  - roles and processors retrieval
 *  - fluent processor addition
 *  - Filter/Rule convenience methods
 *  - basic type consistency
 */
final class FieldDefinitionTest extends TestCase
{
    // ---------------------------------------------------------------------
    // Fakes for isolated testing
    // ---------------------------------------------------------------------
    
    private function makeFakeProcessor(string $label): FieldProcessor
    {
        return new class ($label) implements FieldProcessor {
            public function __construct(public string $label)
            {
            }
            public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
            {
                // Pass value through unchanged for test isolation
                return $value;
            }
            public function __toString(): string
            {
                return $this->label; 
            }
        };
    }
    
    private function makeFakeFilter(): Filter
    {
        return new class implements Filter {
            public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
            {
                return trim((string) $value);
            }
            public function apply(mixed $value, FieldDefinition $field): mixed
            {
                return trim((string) $value); 
            }
            public function __toString(): string
            {
                return 'FakeFilter'; 
            }
        };
    }
    
    private function makeFakeRule(): Rule
    {
        return new class implements Rule {
            public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
            {
                return $value;
            }
            public function validate(mixed $value, FieldDefinition $field): array
            {
                return []; 
            }
            public function __toString(): string
            {
                return 'FakeRule'; 
            }
        };
    }
    
    // ---------------------------------------------------------------------
    // Construction & Getters
    // ---------------------------------------------------------------------
    
    public function testConstructorAssignsAllProperties(): void
    {
        $role = FieldRole::SenderEmail;
        $processor = $this->makeFakeProcessor('P1');
        
        $field = new FieldDefinition(
            name: 'email',
            roles: [$role],
            processors: [$processor]
        );
        
        self::assertSame('email', $field->getName());
        self::assertSame([$role], $field->getRoles());
        self::assertSame([$processor], $field->getProcessors());
    }
    
    public function testDefaultValuesWhenOptionalArgsOmitted(): void
    {
        $field = new FieldDefinition('message');
        
        self::assertSame('message', $field->getName());
        self::assertSame([], $field->getRoles());
        self::assertSame([], $field->getProcessors());
    }
    
    // ---------------------------------------------------------------------
    // Processor management
    // ---------------------------------------------------------------------
    
    public function testAddProcessorAppendsAndReturnsSelf(): void
    {
        $p1 = $this->makeFakeProcessor('one');
        $p2 = $this->makeFakeProcessor('two');
        
        $field = new FieldDefinition('username', processors: [$p1]);
        $result = $field->addProcessor($p2);
        
        self::assertSame($field, $result, 'addProcessor should be fluent');
        self::assertCount(2, $field->getProcessors());
        self::assertSame($p2, $field->getProcessors()[1]);
    }
    
    // ---------------------------------------------------------------------
    // Convenience methods
    // ---------------------------------------------------------------------
    
    public function testAddFilterAddsFilterAsProcessor(): void
    {
        $filter = $this->makeFakeFilter();
        $field = new FieldDefinition('email');
        $field->addFilter($filter);
        
        $processors = $field->getProcessors();
        self::assertCount(1, $processors);
        self::assertSame($filter, $processors[0]);
    }
    
    public function testAddRuleAddsRuleAsProcessor(): void
    {
        $rule = $this->makeFakeRule();
        $field = new FieldDefinition('email');
        $field->addRule($rule);
        
        $processors = $field->getProcessors();
        self::assertCount(1, $processors);
        self::assertSame($rule, $processors[0]);
    }
    
    // ---------------------------------------------------------------------
    // Behavior verification
    // ---------------------------------------------------------------------
    
    public function testChainingMultipleAdditions(): void
    {
        $filter = $this->makeFakeFilter();
        $rule = $this->makeFakeRule();
        
        $field = (new FieldDefinition('comment'))
            ->addFilter($filter)
            ->addRule($rule)
            ->addProcessor($this->makeFakeProcessor('final'));
        
        $processors = $field->getProcessors();
        self::assertCount(3, $processors);
        self::assertSame($filter, $processors[0]);
        self::assertSame($rule, $processors[1]);
        self::assertStringContainsString('final', (string) $processors[2]);
    }
    
    public function testRolesCanContainMultipleEnums(): void
    {
        $roles = [FieldRole::SenderEmail, FieldRole::SenderName];
        $field = new FieldDefinition('email', roles: $roles);
        
        self::assertCount(2, $field->getRoles());
        self::assertSame($roles, $field->getRoles());
    }
    
    public function testCanMixRuleAndFilterProcessors(): void
    {
        $filter = $this->makeFakeFilter();
        $rule = $this->makeFakeRule();
        
        $field = new FieldDefinition('subject', processors: [$filter, $rule]);
        $processors = $field->getProcessors();
        
        self::assertCount(2, $processors);
        self::assertInstanceOf(Filter::class, $processors[0]);
        self::assertInstanceOf(Rule::class, $processors[1]);
    }
}
