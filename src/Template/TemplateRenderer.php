<?php

declare(strict_types = 1);

namespace FormToEmail\Template;

/**
 * Class: TemplateRenderer
 *
 * Handles rendering of email templates from form data.
 *
 * The system supports:
 *  - **Custom templates** with placeholders like `{{field_name}}`
 *  - **Default HTML and text templates** for automatic formatting
 *
 * Template placeholders are replaced directly with sanitized
 * values provided by the form validation layer.
 *
 * Example (custom):
 * ```php
 * $html = TemplateRenderer::render(
 *     "<h1>Message from {{name}}</h1><p>{{message}}</p>",
 *     ['name' => 'Julien', 'message' => 'Hello!']
 * );
 * ```
 *
 * Example (default):
 * ```php
 * $html = TemplateRenderer::defaultHtml($data, "New Contact Message");
 * $text = TemplateRenderer::defaultText($data, "New Contact Message");
 * ```
 */
final class TemplateRenderer
{
    /**
     * Replace `{{field}}` placeholders with actual sanitized values.
     *
     * @param string $template The template string containing placeholders.
     * @param array<string,string> $data Key-value pairs for replacement.
     */
    public static function render(string $template, array $data): string
    {
        $search = [];
        $replace = [];
        
        foreach ($data as $key => $value) {
            $search[] = '{{' . $key . '}}';
            $replace[] = (string)$value;
        }
        
        return str_replace($search, $replace, $template);
    }
    
    /**
     * Default HTML email layout.
     *
     * Generates a responsive, minimal HTML table showing all
     * submitted key-value pairs in a clean two-column format.
     *
     * @param array<string,string> $data
     * @param string $title
     */
    public static function defaultHtml(array $data, string $title = 'Form Submission'): string
    {
        $rows = '';
        
        foreach ($data as $key => $value) {
            $rows .= sprintf(
                '<tr><th style="text-align:left;padding:8px 10px;background:#f8f9fa;border-bottom:1px solid #ddd;">%s</th><td style="padding:8px 10px;border-bottom:1px solid #eee;">%s</td></tr>',
                htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
                nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'))
            );
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
</head>
<body style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#ffffff;color:#333;">
  <h2 style="border-bottom:2px solid #333;padding-bottom:4px;">{$title}</h2>
  <table style="width:100%;border-collapse:collapse;margin-top:10px;">
    {$rows}
  </table>
  <p style="font-size:12px;color:#777;margin-top:20px;">
    Sent automatically by form-to-email library.
  </p>
</body>
</html>
HTML;
    }
    
    /**
     * Default plain-text layout for non-HTML email clients.
     *
     * @param array<string,string> $data
     * @param string $title
     */
    public static function defaultText(array $data, string $title = 'Form Submission'): string
    {
        $lines = [
            $title,
            str_repeat('=', mb_strlen($title)),
            ''
        ];
        
        foreach ($data as $key => $value) {
            $lines[] = sprintf("%s: %s", $key, $value);
        }
        
        return implode("\n", $lines);
    }
}
