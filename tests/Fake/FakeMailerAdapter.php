<?php

declare(strict_types=1);

namespace FormToEmail\Tests\Fake;

use FormToEmail\Mail\MailerAdapter;
use FormToEmail\Mail\MailPayload;

/**
 * Fake mailer used for testing without real SMTP.
 *
 * It simply records payloads in memory.
 */
final class FakeMailerAdapter implements MailerAdapter
{
    /** @var list<MailPayload> */
    public array $sent = [];
    
    public function send(MailPayload $payload): void
    {
        $this->sent[] = $payload;
    }
}
