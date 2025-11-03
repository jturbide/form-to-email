<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Template;

use PHPUnit\Framework\TestCase;
use FormToEmail\Template\TemplateRenderer;

final class TemplateRendererTest extends TestCase
{
    public function testRenderReplacesPlaceholders(): void
    {
        $template = 'Hello {{name}}, your message: {{message}}';
        $data = ['name' => 'Julien', 'message' => 'Hi!'];
        
        $output = TemplateRenderer::render($template, $data);
        
        $this->assertSame('Hello Julien, your message: Hi!', $output);
    }
    
    public function testDefaultHtmlIncludesTable(): void
    {
        $html = TemplateRenderer::defaultHtml(['name' => 'Julien'], 'Test');
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Julien', $html);
    }
    
    public function testDefaultTextFormatsCorrectly(): void
    {
        $text = TemplateRenderer::defaultText(['name' => 'Julien'], 'Title');
        $this->assertStringContainsString('Title', $text);
        $this->assertStringContainsString('name: Julien', $text);
    }
}
