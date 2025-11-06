<?php

declare(strict_types=1);

namespace FormToEmail\Http;

use FormToEmail\Core\{
    FormDefinition,
    ValidationResult,
    ValidationError,
    Logger
};
use FormToEmail\Enum\{
    FieldRole,
    ResponseCode
};
use FormToEmail\Mail\{
    MailerAdapter,
    MailPayload
};
use FormToEmail\Template\TemplateRenderer;
use Throwable;

/**
 * Class: FormToEmailController
 *
 * Central coordinator for processing and delivering form submissions.
 *
 * Responsibilities:
 *  - Parse incoming POST/JSON payloads.
 *  - Validate fields via {@see FormDefinition}.
 *  - Build templated {@see MailPayload}.
 *  - Send mail using {@see MailerAdapter}.
 *  - Return structured response codes.
 *  - Optionally log lifecycle events via {@see Logger}.
 *
 * This controller is self-contained and suitable for:
 *  - Direct HTTP endpoints (see {@see handle()}).
 *  - CLI testing or internal calls (see {@see handleRequest()}).
 */
final readonly class FormToEmailController
{
    /**
     * @param FormDefinition $form Form definition object.
     * @param MailerAdapter $mailer Mail delivery adapter.
     * @param list<string> $recipients Destination addresses.
     * @param string $defaultSubject Fallback subject line.
     * @param Logger|null $logger Optional submission logger.
     * @param string|null $customHtmlTemplate Optional HTML template.
     * @param string|null $customTextTemplate Optional text template.
     */
    public function __construct(
        private FormDefinition $form,
        private MailerAdapter $mailer,
        private array $recipients,
        private string $defaultSubject = 'New Form Submission',
        private ?Logger $logger = null,
        private ?string $customHtmlTemplate = null,
        private ?string $customTextTemplate = null,
    ) {
    }
    
    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------
    
    /**
     * Safely handles a submission in CLI/test contexts.
     *
     * Parses, validates, sends, and logs the lifecycle.
     *
     * @param array<string,string>|null $server Optional $_SERVER override.
     * @param string|null $rawBody Optional raw JSON override.
     *
     * @return array{
     *     code: string,
     *     errors?: array<string, list<ValidationError>>
     * }
     */
    public function handleRequest(?array $server = null, ?string $rawBody = null): array
    {
        $server ??= $_SERVER;
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        
        // Allow CORS preflights.
        if ($method === 'OPTIONS') {
            return ['code' => ResponseCode::OK->value];
        }
        
        // Reject non-POST requests early.
        if ($method !== 'POST') {
            return ['code' => ResponseCode::INVALID_METHOD->value];
        }
        
        // Parse JSON body.
        $body = $rawBody ?? file_get_contents('php://input');
        $input = json_decode($body === false ? '' : $body, true);
        
        if (!is_array($input)) {
            return ['code' => ResponseCode::INVALID_JSON->value];
        }
        
        // Log the incoming submission (fields or full raw input)
        $this->logger?->recordSubmissionStart($input);
        
        // Validate the payload.
        $result = $this->form->process($input);
        
        if ($result->failed()) {
            // Log validation errors with structured field data.
            $this->logger?->recordValidationErrors($result->allErrors());
            
            return [
                'code' => ResponseCode::VALIDATION_ERROR->value,
                'errors' => $result->allErrors(),
            ];
        }
        
        // Try to send the email.
        try {
            $payload = $this->buildMail($result);
            $this->mailer->send($payload);
            
            // Log successful submission including recipients and subject.
            $this->logger?->recordSuccess($result->data, [
                'to' => $this->recipients,
                'subject' => $payload->subject,
            ]);
            
            return ['code' => ResponseCode::OK->value];
        } catch (Throwable $e) {
            // Log mail or template failure with exception context.
            $this->logger?->recordException($e);
            
            return ['code' => ResponseCode::MAIL_FAILURE->value];
        }
    }
    
    /**
     * Production HTTP entrypoint — emits JSON directly.
     *
     * Never returns (terminates via `exit()`).
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
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    // ---------------------------------------------------------------------
    // Internal Helpers
    // ---------------------------------------------------------------------
    
    /**
     * Builds a {@see MailPayload} from the validated data.
     *
     * @param ValidationResult $result Validated form data container.
     */
    private function buildMail(ValidationResult $result): MailPayload
    {
        $data = $result->data;
        
        $replyToEmail = null;
        $replyToName = null;
        $subjectParts = [];
        
        // Extract sender/subject info from field roles.
        foreach ($this->form->fields() as $field) {
            $value = $data[$field->name] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            
            foreach ($field->roles as $role) {
                match ($role) {
                    FieldRole::SenderEmail => $replyToEmail = $value,
                    FieldRole::SenderName => $replyToName = $value,
                    FieldRole::Subject => $subjectParts[] = $value,
                    default => null,
                };
            }
        }
        
        // Fallback subject.
        $subject = $subjectParts
            ? implode(' - ', $subjectParts)
            : $this->defaultSubject;
        
        // Render templates.
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
