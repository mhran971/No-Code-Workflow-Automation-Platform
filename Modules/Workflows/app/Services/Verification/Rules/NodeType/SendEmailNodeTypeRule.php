<?php

namespace Modules\Workflows\Services\Verification\Rules\NodeType;

use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class SendEmailNodeTypeRule implements NodeTypeRule
{
    use VariableAvailability;

    protected const CODE_PREFIX = 'send_email';

    public function nodeType(): string
    {
        return 'send-email';
    }

    public function verify(
        array $node,
        int $index,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
    ): void {
        $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];

        $to = trim((string) ($config['to'] ?? ''));
        if ($to === '') {
            $result->addError('send_email.to_missing', 'Send email node must specify a recipient.', "nodes[{$index}].config.to", $nodeId);
        } else {
            $this->validateEmailOrVar($to, "nodes[{$index}].config.to", 'to', $nodeId, $graph, $result);
        }

        foreach (['cc', 'bcc'] as $field) {
            $val = trim((string) ($config[$field] ?? ''));
            if ($val !== '') {
                $this->validateEmailOrVar($val, "nodes[{$index}].config.{$field}", $field, $nodeId, $graph, $result);
            }
        }

        $subject = trim((string) ($config['subject'] ?? ''));
        if ($subject === '') {
            $result->addError('send_email.subject_missing', 'Send email node must have a subject.', "nodes[{$index}].config.subject", $nodeId);
        } else {
            $this->validateTemplateVariables($subject, "nodes[{$index}].config.subject", $nodeId, self::CODE_PREFIX, $graph, $result);
        }

        $body = trim((string) ($config['body'] ?? ''));
        if ($body === '') {
            $result->addError('send_email.body_missing', 'Send email node must have a body.', "nodes[{$index}].config.body", $nodeId);
        } else {
            $this->validateTemplateVariables($body, "nodes[{$index}].config.body", $nodeId, self::CODE_PREFIX, $graph, $result);
        }
    }

    /**
     * Value must be either a single {{context.<key>}} / {{customer.<key>}} variable
     * or a plain email address. Nothing else is accepted.
     */
    private function validateEmailOrVar(
        string $value,
        string $path,
        string $fieldName,
        ?string $nodeId,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
    ): void {
        if (str_starts_with($value, '{{')) {
            // Must be exactly one complete template variable and nothing else
            if (! preg_match('/^\{\{\s*(context|customer)\.([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}$/', $value, $m)) {
                $result->addError(
                    "send_email.{$fieldName}_invalid",
                    "The {$fieldName} field has an invalid variable '{$value}'. Use {{context.<key>}} or {{customer.<key>}} with a single identifier.",
                    $path,
                    $nodeId,
                );

                return;
            }

            $variable = trim(substr($value, 2, -2)); // strip {{ }}
            if (str_starts_with($variable, 'context.') && $nodeId !== null) {
                $available = $this->collectAvailableContextKeys($nodeId, $graph);
                if ($available !== null) {
                    $this->validateContextVariableExists($variable, $available, self::CODE_PREFIX, $path, $nodeId, $result);
                }
            }
        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $result->addError(
                "send_email.{$fieldName}_invalid",
                "The {$fieldName} field must be a valid email address or a variable like {{context.email}}.",
                $path,
                $nodeId,
            );
        }
    }
}
