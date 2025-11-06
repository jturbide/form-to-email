<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use FormToEmail\Core\ErrorDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Core\FieldProcessor;
use FormToEmail\Core\FormContext;
use FormToEmail\Core\FormDefinition;
use FormToEmail\Core\ValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FormToEmail\Core\FormDefinition
 *
 * Ensures correct orchestration of form processing:
 *  - Field registration and retrieval
 *  - Processor sequencing and data propagation
 *  - Validation error aggregation
 *  - Immutable result generation
 */
final class FormDefinitionTest extends TestCase
{
    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------
    
    /** Creates a dummy processor with controllable behavior. */
    private function makeProcessor(
        ?callable $fn = null,
        ?string $label = null
    ): FieldProcessor {
        return new class ($fn, $label) implements FieldProcessor {
            /** @var callable|null */
            private mixed $fn;
            private ?string $label;
            
            public function __construct(?callable $fn = null, ?string $label = null)
            {
                $this->fn = $fn;
                $this->label = $label;
            }
            
            public function process(mixed $value, FieldDefinition $field, FormContext $context): mixed
            {
                if ($this->fn !== null) {
                    return ($this->fn)($value, $field, $context);
                }
                return $value;
            }
            
            public function __toString(): string
            {
                return $this->label ?? 'anonymous';
            }
        };
    }
    
    // ---------------------------------------------------------------------
    // Core behavior tests
    // ---------------------------------------------------------------------
    
    public function testAddAndRetrieveFields(): void
    {
        $form = new FormDefinition();
        
        $fieldA = new FieldDefinition('email');
        $fieldB = new FieldDefinition('name');
        
        $form->add($fieldA)->add($fieldB);
        
        $fields = $form->fields();
        
        self::assertCount(2, $fields);
        self::assertArrayHasKey('email', $fields);
        self::assertSame($fieldA, $fields['email']);
        self::assertSame($fieldB, $fields['name']);
    }
    
    public function testProcessExecutesAllProcessorsSequentially(): void
    {
        $invocations = [];
        
        $processor1 = $this->makeProcessor(function ($value) use (&$invocations) {
            $invocations[] = 'p1';
            return trim((string) $value);
        });
        
        $processor2 = $this->makeProcessor(function ($value) use (&$invocations) {
            $invocations[] = 'p2';
            return strtoupper((string) $value);
        });
        
        $field = new FieldDefinition('name', processors: [$processor1, $processor2]);
        $form = new FormDefinition();
        $form->add($field);
        
        $result = $form->process(['name' => '  julien  ']);
        
        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertTrue($result->isValid());
        self::assertSame('JULIEN', $result->allData()['name']);
        self::assertSame(['p1', 'p2'], $invocations);
    }
    
    public function testProcessorCanAddErrorToContext(): void
    {
        $errorProcessor = $this->makeProcessor(function ($value, FieldDefinition $field, FormContext $context) {
            $context->addError($field->getName(), new ErrorDefinition('invalid', 'bad'));
            return $value;
        });
        
        $field = new FieldDefinition('age', processors: [$errorProcessor]);
        $form = new FormDefinition();
        $form->add($field);
        
        $result = $form->process(['age' => 42]);
        
        self::assertFalse($result->isValid());
        self::assertArrayHasKey('age', $result->allErrors());
        self::assertSame('invalid', $result->allErrors()['age'][0]->getCode());
    }
    
    public function testMultipleFieldsAggregateResults(): void
    {
        $f1 = new FieldDefinition('email', processors: [
            $this->makeProcessor(fn($v) => strtolower(trim((string) $v))),
        ]);
        $f2 = new FieldDefinition('name', processors: [
            $this->makeProcessor(fn($v) => ucfirst((string) $v)),
        ]);
        
        $form = new FormDefinition();
        $form->add($f1)->add($f2);
        
        $result = $form->process([
            'email' => '  USER@EXAMPLE.COM ',
            'name'  => 'julien',
        ]);
        
        self::assertTrue($result->isValid());
        self::assertSame('user@example.com', $result->allData()['email']);
        self::assertSame('Julien', $result->allData()['name']);
        self::assertEmpty($result->allErrors());
    }
    
    public function testMissingFieldInputDefaultsToNull(): void
    {
        $processor = $this->makeProcessor(fn($v) => $v ?? 'default');
        $field = new FieldDefinition('country', processors: [$processor]);
        $form = (new FormDefinition())->add($field);
        
        $result = $form->process([]); // no input
        self::assertSame('default', $result->allData()['country']);
    }
    
    public function testProcessDoesNotModifyOriginalInput(): void
    {
        $input = ['name' => ' julien '];
        $form = (new FormDefinition())
            ->add(new FieldDefinition('name', processors: [
                $this->makeProcessor(fn($v) => trim((string) $v))
            ]));
        
        $form->process($input);
        
        // Ensure original array remains intact
        self::assertSame(['name' => ' julien '], $input);
    }
    
    public function testProcessAlwaysReturnsValidationResult(): void
    {
        $form = new FormDefinition();
        $res = $form->process(['any' => 'thing']);
        self::assertInstanceOf(ValidationResult::class, $res);
    }
    
    // ---------------------------------------------------------------------
    // Edge cases and stability checks
    // ---------------------------------------------------------------------
    
    public function testEmptyFormReturnsValidResult(): void
    {
        $form = new FormDefinition();
        $result = $form->process([]);
        self::assertTrue($result->isValid());
        self::assertSame([], $result->allData());
        self::assertSame([], $result->allErrors());
    }
    
    public function testProcessorsMayMutateContextDirectly(): void
    {
        $proc = $this->makeProcessor(function ($value, FieldDefinition $field, FormContext $context) {
            $context->setValue($field->getName(), 'mutated');
            return 'ignored';
        });
        
        $form = (new FormDefinition())->add(new FieldDefinition('foo', processors: [$proc]));
        $res = $form->process(['foo' => 'original']);
        
        // Processor set value directly, and process() overwrites context as per design
        self::assertSame('ignored', $res->allData()['foo']);
    }
}
