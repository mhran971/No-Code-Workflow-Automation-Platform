<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Modules\Integrations\Models\IntegrationConnection;
use Modules\Integrations\Services\Gmail\GmailClient;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

class SendEmailExecutor implements NodeExecutor
{
    public function __construct(protected GmailClient $gmail) {}

    public function type(): string
    {
        return 'send-email';
    }

    public function category(): NodeCategory
    {
        return NodeCategory::Action;
    }

    public function execute(NodeExecutionContext $context): NodeExecutionResult
    {
        $config = $context->config();

        $to = $context->render((string) ($config['to'] ?? ''));
        $subject = $context->render((string) ($config['subject'] ?? ''));
        $body = $context->render((string) ($config['body'] ?? ''));
        $cc = isset($config['cc']) && $config['cc'] !== '' ? $context->render((string) $config['cc']) : null;
        $bcc = isset($config['bcc']) && $config['bcc'] !== '' ? $context->render((string) $config['bcc']) : null;

        // Deterministic Message-ID derived from the execution's idempotency key so that
        // re-delivery of the same job (at-least-once queue) does not produce a duplicate send
        // at the receiving MTA (RFC 5321 §4.1.1.4 de-dup on Message-ID).
        $messageId = 'workflow-'.$context->idempotencyKey().'@mail.local';

        $connection = IntegrationConnection::query()
            ->where('integration_provider_id', 'google')
            ->where('tenant_id', $context->instance()->tenant_id)
            ->first();

        if ($connection === null) {
            return NodeExecutionResult::fail(
                'No Google connection found for this tenant. Connect Google via Integrations to enable Gmail sending.',
                retryable: false,
            );
        }

        $result = $this->gmail->send($connection, $to, $subject, $body, $cc, $bcc, $messageId);

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            [
                'sent_to' => $to,
                'message_id' => $messageId,
                'gmail_id' => $result['id'] ?? null,
                'thread_id' => $result['threadId'] ?? null,
            ],
        );
    }
}
