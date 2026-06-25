<?php

namespace Modules\Workflows\Services\Execution\Executors;

use Illuminate\Support\Facades\Mail;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\Contracts\NodeExecutor;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\NodeExecutionResult;

class SendEmailExecutor implements NodeExecutor
{
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

        Mail::html($body, function ($message) use ($to, $subject, $cc, $bcc, $messageId): void {
            $message->to($to)->subject($subject);

            if ($cc !== null) {
                $message->cc($cc);
            }

            if ($bcc !== null) {
                $message->bcc($bcc);
            }

            $message->withSymfonyMessage(function (\Symfony\Component\Mime\Email $email) use ($messageId): void {
                $email->getHeaders()->remove('Message-ID');
                $email->getHeaders()->addIdHeader('Message-ID', $messageId);
            });
        });

        return NodeExecutionResult::proceed(
            $context->plan()->outgoing($context->nodeKey()),
            ['sent_to' => $to, 'message_id' => $messageId],
        );
    }
}
