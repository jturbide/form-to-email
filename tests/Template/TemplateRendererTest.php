<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Template;

use PHPUnit\Framework\TestCase;
use FormToEmail\Template\TemplateRenderer;

final class TemplateRendererTest extends TestCase
{
    // -------------------------------------------------------------------------
    // render()
    // -------------------------------------------------------------------------
    
    public function testRenderReplacesPlaceholders(): void
    {
        $template = 'Hello {{name}}, your message: {{message}}';
        $data = ['name' => 'Julien', 'message' => 'Hi!'];
        
        $output = TemplateRenderer::render($template, $data);
        
        $this->assertSame('Hello Julien, your message: Hi!', $output);
    }
    
    public function testRenderIgnoresUnknownPlaceholders(): void
    {
        $template = 'Hello {{name}}, {{unknown}} world!';
        $output = TemplateRenderer::render($template, ['name' => 'Julien']);
        $this->assertSame('Hello Julien, {{unknown}} world!', $output);
    }
    
    public function testRenderWithEmptyDataReturnsOriginalTemplate(): void
    {
        $template = 'Nothing changes here.';
        $this->assertSame($template, TemplateRenderer::render($template, []));
    }
    
    public function testRenderHandlesSpecialCharacters(): void
    {
        $template = 'Email: {{email}}';
        $data = ['email' => 'test+foo@example.com'];
        $output = TemplateRenderer::render($template, $data);
        $this->assertSame('Email: test+foo@example.com', $output);
    }
    
    // -------------------------------------------------------------------------
    // defaultHtml()
    // -------------------------------------------------------------------------
    
    public function testDefaultHtmlIncludesTitleAndTable(): void
    {
        $html = TemplateRenderer::defaultHtml(['name' => 'Julien'], 'Test Email');
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Test Email', $html);
        $this->assertStringContainsString('Julien', $html);
    }
    
    public function testDefaultHtmlEscapesHtmlEntities(): void
    {
        $html = TemplateRenderer::defaultHtml(['comment' => '<b>bold</b>'], 'XSS Test');
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
    }
    
    public function testDefaultHtmlConvertsNewlinesToBreaks(): void
    {
        $html = TemplateRenderer::defaultHtml(['message' => "Line1\nLine2"], 'Test');
        $this->assertMatchesRegularExpression('/Line1<br\s*\/?>\s*Line2/', $html);
    }
    
    public function testDefaultHtmlWithMultipleFields(): void
    {
        $html = TemplateRenderer::defaultHtml([
            'name' => 'Julien',
            'email' => 'julien@example.com'
        ], 'Multi');
        $this->assertStringContainsString('julien@example.com', $html);
        $this->assertSame(2, substr_count($html, '<tr>'));
    }
    
    // -------------------------------------------------------------------------
    // defaultText()
    // -------------------------------------------------------------------------
    
    public function testDefaultTextFormatsKeyValuePairs(): void
    {
        $text = TemplateRenderer::defaultText(['name' => 'Julien', 'email' => 'a@b.c'], 'Contact');
        $this->assertStringContainsString('name: Julien', $text);
        $this->assertStringContainsString('email: a@b.c', $text);
        $this->assertStringContainsString('Contact', $text);
    }
    
    public function testDefaultTextTitleUnderlineMatchesLength(): void
    {
        $title = 'Form Message';
        $text = TemplateRenderer::defaultText([], $title);
        $lines = explode("\n", $text);
        $this->assertSame(strlen($title), strlen(trim($lines[1])));
        $this->assertSame(str_repeat('=', strlen($title)), trim($lines[1]));
    }
    
    public function testDefaultTextHandlesEmptyData(): void
    {
        $text = TemplateRenderer::defaultText([], 'Empty Test');
        $this->assertStringContainsString('Empty Test', $text);
        $this->assertStringNotContainsString(':', $text, 'No key/value pairs should appear');
    }
    
    public function testDefaultTextHandlesUnicodeCharacters(): void
    {
        $text = TemplateRenderer::defaultText(['message' => 'Olá, 世界'], 'Unicode');
        $this->assertStringContainsString('Olá, 世界', $text);
    }
}
