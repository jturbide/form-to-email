<?php

declare(strict_types=1);

namespace FormToEmail\Http;

use FormToEmail\Core\FormDefinition;
use FormToEmail\Core\ValidationResult;
use FormToEmail\Enum\FieldRole;
use FormToEmail\Enum\ResponseCode;
use FormToEmail\Mail\MailerAdapter;
use FormToEmail\Mail\MailPayload;
use FormToEmail\Template\TemplateRenderer;

/**
 * Class: FormToEmailController
 *
 * The central coordinator that:
 *  - Parses incoming form data (JSON or POST),
 *  - Runs validation using {@see FormDefinition},
 *  - Renders email templates (HTML + text),
 *  - Sends the email via a {@see MailerAdapter},
 *  - Returns a structured JSON response with a stable {@see ResponseCode}.
 *
 * Designed for both:
 *  - Production HTTP entrypoints (via handle())
 *  - Unit tests or internal calls (via handleRequest())
 */
final class FormToEmailController
{
    public function __construct(
        private readonly FormDefinition $form,
        private readonly MailerAdapter $mailer,
        /**
         * Destination email addresses (can include multiple).
         *
         * @var list<string>
         */
        private readonly array $recipients,
        /**
         * Default subject line if none is provided via field roles.
         */
        private readonly string $defaultSubject = 'New Form Submission',
        /**
         * Optional custom HTML template (with {{placeholders}}).
         */
        private readonly ?string $customHtmlTemplate = null,
        /**
         * Optional custom plain text template (with {{placeholders}}).
         */
        private readonly ?string $customTextTemplate = null,
    ) {
    }
    
    /**
     * Handles a request in a test- or CLI-safe way.
     *
     * @param array<string, string>|null  $server   Optional server globals override
     * @param string|null $rawBody  Optional JSON body override
     *
     * @return array{code:string,errors?:array<string,list<string>>}
     */
    public function handleRequest(?array $server = null, ?string $rawBody = null): array
    {
        $server ??= $_SERVER;
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'OPTIONS') {
            return ['code' => ResponseCode::OK->value];
        }
        
        if ($method !== 'POST') {
            return ['code' => ResponseCode::INVALID_METHOD->value];
        }
        
        $body = $rawBody ?? file_get_contents('php://input');
        $input = json_decode($body === false ? '' : $body, true);
        if (!is_array($input)) {
            return ['code' => ResponseCode::INVALID_JSON->value];
        }
        
        $result = $this->form->validate($input);
        if ($result->failed()) {
            return [
                'code' => ResponseCode::VALIDATION_ERROR->value,
                'errors' => $result->errors,
            ];
        }
        
        try {
            $payload = $this->buildMail($result);
            $this->mailer->send($payload);
            
            return ['code' => ResponseCode::OK->value];
        } catch (\Throwable $e) {
            error_log('form-to-email mail failure: ' . $e->getMessage());
            return ['code' => ResponseCode::MAIL_FAILURE->value];
        }
    }
    
    /**
     * Production entrypoint — emits JSON and terminates.
     */
    public function handle(): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        $response = $this->handleRequest();
        
        http_response_code(match ($response['code']) {
            ResponseCode::OK->value => 200,
            ResponseCode::INVALID_METHOD->value => 405,
            ResponseCode::INVALID_JSON->value => 400,
            ResponseCode::VALIDATION_ERROR->value => 422,
            ResponseCode::MAIL_FAILURE->value => 502,
            default => 500,
        });
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
        exit();
    }
    
    /**
     * Internal helper: builds an immutable MailPayload object.
     */
    private function buildMail(ValidationResult $result): MailPayload
    {
        $data = $result->data;
        
        $replyToEmail = null;
        $replyToName  = null;
        $subjectParts = [];
        
        foreach ($this->form->fields() as $field) {
            $value = $data[$field->name] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            
            foreach ($field->roles as $role) {
                match ($role) {
                    FieldRole::SenderEmail => $replyToEmail = $value,
                    FieldRole::SenderName  => $replyToName  = $value,
                    FieldRole::Subject     => $subjectParts[] = $value,
                    default => null,
                };
            }
        }
        
        $subject = $subjectParts
            ? implode(' - ', $subjectParts)
            : $this->defaultSubject;
        
        $htmlBody = $this->customHtmlTemplate !== null
            ? TemplateRenderer::render($this->customHtmlTemplate, $data)
            : TemplateRenderer::defaultHtml($data, $subject);
        
        $textBody = $this->customTextTemplate !== null
            ? TemplateRenderer::render($this->customTextTemplate, $data)
            : TemplateRenderer::defaultText($data, $subject);
        
        return new MailPayload(
            to: $this->recipients,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            replyToEmail: $replyToEmail,
            replyToName: $replyToName,
        );
    }
}
