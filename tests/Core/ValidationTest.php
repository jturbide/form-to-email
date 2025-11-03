<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Core;

use PHPUnit\Framework\TestCase;
use FormToEmail\Core\FormDefinition;
use FormToEmail\Core\FieldDefinition;
use FormToEmail\Validation\Rules\RequiredRule;
use FormToEmail\Validation\Rules\EmailRule;
use FormToEmail\Validation\Rules\LengthRule;

final class ValidationTest extends TestCase
{
    public function testValidDataPassesValidation(): void
    {
        $form = (new FormDefinition())
            ->add(new FieldDefinition('name', rules: [new RequiredRule(), new LengthRule(2, 50)]))
            ->add(new FieldDefinition('email', rules: [new RequiredRule(), new EmailRule()]));
        
        $input = ['name' => 'Julien', 'email' => 'moi@jturbide.com'];
        $result = $form->validate($input);
        
        $this->assertTrue($result->valid);
        $this->assertSame([], $result->errors);
    }
    
    public function testMissingRequiredFieldFails(): void
    {
        $form = (new FormDefinition())
            ->add(new FieldDefinition('email', rules: [new RequiredRule()]));
        
        $input = [];
        $result = $form->validate($input);
        
        $this->assertFalse($result->valid);
        $this->assertArrayHasKey('email', $result->errors);
    }
    
    public function testInvalidEmailProducesError(): void
    {
        $form = (new FormDefinition())
            ->add(new FieldDefinition('email', rules: [new EmailRule()]));
        
        $input = ['email' => 'not-an-email'];
        $result = $form->validate($input);
        
        $this->assertFalse($result->valid);
        $this->assertContains('invalid_email', $result->errors['email']);
    }
}
