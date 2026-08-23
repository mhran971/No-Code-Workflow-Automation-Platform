<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\KnowledgeBase\Models\Document;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\Data\WorkflowVerificationResult;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;

class ContextualVerificationRule implements VerificationRule
{
    public function skipForSegment(): bool
    {
        return true;
    }

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void {
        if ($workflow === null) {
            return;
        }

        $this->verifyHumanTaskAssignees($graph, $workflow, $result);
        $this->verifyKnowledgeBaseDocuments($graph, $workflow, $result);
    }

    protected function verifyHumanTaskAssignees(
        WorkflowDefinitionGraph $graph,
        Workflow $workflow,
        WorkflowVerificationResult $result,
    ): void {
        foreach ($graph->nodes() as $index => $node) {
            if (($node['type'] ?? null) !== 'task-node') {
                continue;
            }

            $assignee = $node['config']['assignTo'] ?? null;

            if ($assignee === null || $assignee === '' || ! is_numeric($assignee)) {
                continue;
            }

            $assigneeId = (int) $assignee;
            $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
            $path = "nodes[{$index}].config.assignTo";
            $userExists = User::query()
                ->where('id', $assigneeId)
                ->where('tenant_id', (int) $workflow->tenant_id)
                ->where('is_active', true)
                ->exists();

            if (! $userExists) {
                $result->addError('context.assignee_unknown', "Human task assignee '{$assigneeId}' must be an active user in this tenant.", $path, $nodeId);

                continue;
            }

            $memberExists = TeamMembership::query()
                ->where('tenant_id', (int) $workflow->tenant_id)
                ->where('team_id', (int) $workflow->team_id)
                ->where('user_id', $assigneeId)
                ->where('status', 'active')
                ->exists();

            if (! $memberExists) {
                $result->addError('context.assignee_not_team_member', "Human task assignee '{$assigneeId}' must be an active member of the workflow team.", $path, $nodeId);
            }
        }
    }

    protected function verifyKnowledgeBaseDocuments(
        WorkflowDefinitionGraph $graph,
        Workflow $workflow,
        WorkflowVerificationResult $result,
    ): void {
        foreach ($graph->nodes() as $index => $node) {
            if (! str_starts_with((string) ($node['type'] ?? ''), 'ai-')) {
                continue;
            }

            // `knowledgeBaseDocuments` is the field name the node's config schema actually seeds
            // (see NodeDefinitionSeeder); `kbDocs` is kept as a legacy alias for older definitions.
            $field = array_key_exists('knowledgeBaseDocuments', $node['config'] ?? []) ? 'knowledgeBaseDocuments' : 'kbDocs';
            $kbDocs = $node['config'][$field] ?? null;

            if ($kbDocs === null || $kbDocs === []) {
                continue;
            }

            $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;

            if (! is_array($kbDocs)) {
                $result->addError('context.kb_docs_invalid', 'Knowledge Base documents must be an array of document IDs.', "nodes[{$index}].config.{$field}", $nodeId);

                continue;
            }

            foreach ($kbDocs as $docIndex => $docId) {
                if (! is_numeric($docId)) {
                    $result->addError('context.kb_doc_id_invalid', 'Knowledge Base document references must be numeric IDs.', "nodes[{$index}].config.{$field}[{$docIndex}]", $nodeId);

                    continue;
                }

                $exists = Document::query()
                    ->where('id', (int) $docId)
                    ->where('tenant_id', (int) $workflow->tenant_id)
                    ->exists();

                if (! $exists) {
                    $result->addError('context.kb_doc_unknown', "Knowledge Base document '{$docId}' does not exist for this tenant.", "nodes[{$index}].config.{$field}[{$docIndex}]", $nodeId);
                }
            }
        }
    }
}
