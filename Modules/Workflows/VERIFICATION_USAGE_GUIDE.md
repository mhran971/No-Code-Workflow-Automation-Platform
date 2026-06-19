# Workflow Verification - Usage Guide

## Quick Start

### Basic Usage: Validate & Update Draft

```php
use Modules\Workflows\Services\WorkflowDefinitionValidator;

// Inject the validator
public function updateDraftAction(WorkflowDefinitionValidator $validator) {
    // Get workflow and new definition from request
    $workflow = Workflow::find($workflowId);
    $newDefinition = $request->input('definition');
    
    // Validate
    $validation = $validator->validate($newDefinition, $workflow, $user);
    
    if ($validation['is_publishable']) {
        // No errors - safe to save
        $workflow->update([
            'draft_definition' => $newDefinition,
            'draft_revision' => $workflow->draft_revision + 1,
        ]);
        
        return response()->json([
            'saved' => true,
            'validation' => $validation,
        ]);
    } else {
        // Has errors - don't save
        return response()->json([
            'saved' => false,
            'errors' => $validation['errors'],
            'validation' => $validation,
        ], 422);
    }
}
```

### Validation-Only (No Save)

```php
// Check validation without modifying workflow
$validation = $validator->validate($definition, $workflow, $user);

if (count($validation['issues']) > 0) {
    $errors = $validation['errors'];
    $warnings = $validation['warnings'];
    // Show to user but don't save
}
```

### Before Publishing

```php
// In WorkflowManagementService::publish()
$validation = $this->definitionValidator->validate(
    $workflow->draft_definition, 
    $workflow, 
    $actor
);

if (! $validation['is_publishable']) {
    throw ValidationException::withMessages([
        'definition' => $validation['errors'],
    ]);
}

// Create version, update workflow...
```

---

## Understanding Validation Results

### Response Structure

```php
[
    'is_publishable' => true|false,
    'summary' => [
        'errors' => 0,
        'warnings' => 2,
    ],
    'issues' => [
        [
            'severity' => 'error|warning',
            'code' => 'error.code',
            'message' => 'Human-readable message',
            'path' => 'nodes[0].config.expression',
            'node_id' => 'my_node_id',
            'edge_id' => null,
        ],
        // ...
    ],
    'errors' => [
        'Error message 1',
        'Error message 2',
    ],
    'warnings' => [
        'Warning message 1',
    ],
]
```

### Decision Logic

```php
$validation = $validator->validate($definition, $workflow, $user);

// Can publish?
if ($validation['is_publishable']) {
    // No errors - proceed with publish
} else {
    // Has errors - show to user
}

// Show detailed info?
$issues = $validation['issues'];
foreach ($issues as $issue) {
    $severity = $issue['severity'];  // 'error' or 'warning'
    $code = $issue['code'];           // Machine-readable
    $message = $issue['message'];     // Human-readable
    $path = $issue['path'];           // Where in definition
}

// Simple error list?
$allErrors = $validation['errors'];  // Just messages
```

---

## Common Issues & Solutions

### Issue: Missing Trigger

**Error**: 
```
Code: trigger.missing
Message: "Workflow trigger must be configured."
```

**Solution**:
```php
// Add trigger to definition
$definition['trigger'] = [
    'type' => 'webhook-trigger',
    'config' => [
        'webhookUrl' => 'https://example.com/webhook',
    ],
];
```

---

### Issue: Unreachable Node

**Error**:
```
Code: graph.unreachable
Message: "Node 'approval_step' is not reachable from the entry node."
Node ID: 'approval_step'
```

**Cause**: Node exists but no edges connect it from the entry point.

**Solution**: Add an edge from entry or another node to this node.

```php
// Before:
Edges: [
    ['source' => 'start', 'target' => 'step_1'],
    // 'approval_step' not connected
]

// After:
Edges: [
    ['source' => 'start', 'target' => 'step_1'],
    ['source' => 'step_1', 'target' => 'approval_step'],
]
```

---

### Issue: Node Has No Outgoing Edge

**Error**:
```
Code: graph.outgoing_missing
Message: "Node 'process_data' must have at least one outgoing edge."
Node ID: 'process_data'
```

**Solution**: Mark as terminal node or add outgoing edge.

```php
// Option 1: Mark as terminal
['id' => 'process_data', 'type' => 'action', 'is_terminal' => true]

// Option 2: Add outgoing edge
Edges: [
    ['source' => 'process_data', 'target' => 'complete'],
]
```

---

### Issue: Invalid Conditional Expression

**Error**:
```
Code: expression.condition_invalid
Message: "Conditional edge expression is invalid: Expression must evaluate to a boolean value."
```

**Cause**: Expression doesn't evaluate to boolean (e.g., arithmetic).

**Invalid Examples**:
```php
'condition_expression' => 'x + 5'           // Arithmetic - returns number
'condition_expression' => 'name'             // Just identifier - ambiguous type
'condition_expression' => 'user.getName()'   // Method calls not supported
```

**Valid Examples**:
```php
'condition_expression' => 'x > 5'           // Comparison → boolean
'condition_expression' => 'x > 5 && y < 10' // Logical AND → boolean
'condition_expression' => 'status == "active"'  // Equality → boolean
'condition_expression' => '!is_deleted'     // Negation → boolean
```

---

### Issue: Unknown Node Type

**Error**:
```
Code: node.type_unknown
Message: "Workflow node type 'my-custom-node' is not active or does not exist."
```

**Cause**: Node type not defined in database or marked inactive.

**Solution**:
1. Check database for available node types
2. Use an active node type
3. Or create/activate the node type

```php
// Check available types
$activeNodes = Node::where('is_active', true)->pluck('type');
// ['human-task', 'api-call', 'conditional', ...]

// Use valid type
['id' => 'n1', 'type' => 'human-task']
```

---

### Issue: Invalid Assignee

**Error**:
```
Code: context.assignee_not_team_member
Message: "Human task assignee '42' must be an active member of the workflow team."
```

**Solution**: Ensure user is:
1. In the same tenant as workflow
2. Active (is_active: true)
3. Member of the workflow's team with status 'active'

```php
// Verify before assigning
$userId = 42;
$teamId = $workflow->team_id;

$isMember = TeamMembership::where('team_id', $teamId)
    ->where('user_id', $userId)
    ->where('status', 'active')
    ->exists();

if ($isMember) {
    $nodeConfig['assignee'] = $userId;
}
```

---

### Issue: Duplicate Node IDs

**Error**:
```
Code: node.id_duplicate
Message: "Workflow node id 'start' is duplicated."
```

**Solution**: Ensure each node has unique ID.

```php
// Bad:
$nodes = [
    ['id' => 'start', 'type' => 'trigger'],
    ['id' => 'start', 'type' => 'action'],  // Duplicate!
]

// Good:
$nodes = [
    ['id' => 'start', 'type' => 'trigger'],
    ['id' => 'process', 'type' => 'action'],
]
```

---

### Issue: Unknown Knowledge Base Document

**Error**:
```
Code: context.kb_doc_unknown
Message: "Knowledge Base document '999' does not exist for this tenant."
```

**Solution**: Ensure document ID is valid and belongs to same tenant.

```php
// Verify document exists
$docExists = Document::where('id', 999)
    ->where('tenant_id', $workflow->tenant_id)
    ->exists();

if ($docExists) {
    $nodeConfig['kbDocs'] = [999];
}
```

---

### Issue: Unstructured Cycle

**Error**:
```
Code: graph.unstructured_cycle
Message: "Unstructured cycle detected from 'step_1' to 'step_2'."
```

**Cause**: Edges create a loop but no explicit loop node.

**Bad Structure**:
```
step_1 → step_2 → step_1  (cycle, no loop node)
```

**Good Structure (Option 1): Use explicit loop node**
```
step_1 → [loop] → step_2 → step_1
```

**Good Structure (Option 2): Remove cycle**
```
step_1 → step_2 → end
```

---

### Issue: No Entry Node

**Error**:
```
Code: graph.entry_missing
Message: "Workflow must have exactly one entry node."
```

**Solution**: Mark one node as entry point OR ensure one node has no incoming edges.

```php
// Option 1: Explicit entry
['id' => 'start', 'is_entry_point' => true]

// Option 2: Implicit entry (no incoming edges)
Edges: [] // start has no incoming edges
```

---

## Integration Examples

### Form Validation in API

```php
// Controller
public function updateDraft(WorkflowDefinitionValidator $validator, Request $request)
{
    $workflow = Workflow::findOrFail($request->workflow_id);
    $definition = $request->input('definition');
    
    $validation = $validator->validate($definition, $workflow, auth()->user());
    
    return response()->json([
        'valid' => $validation['is_publishable'],
        'errors' => $validation['errors'],
        'warnings' => $validation['warnings'],
        'issues' => $validation['issues'],
    ]);
}
```

### Real-Time Validation (No Save)

```php
// Use validate_only flag
public function validateDraft(Request $request, WorkflowManagementService $service)
{
    $result = $service->updateDraft(
        $user,
        $workflow,
        array_merge($request->input(), ['validate_only' => true])
    );
    
    return response()->json($result['validation']);
}
```

### Progressive Validation

```php
// Show issues as user builds workflow
$issues = [];
$stage = 1;

// Stage 1: Basic structure
$basic = $validator->validate($definition, null, null);
$issues['syntax'] = $basic['errors'];

if (empty($issues['syntax'])) {
    // Stage 2: Logic with workflow context
    $logical = $validator->validate($definition, $workflow, $user);
    $issues['logic'] = $logical['errors'];
}

return response()->json($issues);
```

### Batch Validation

```php
// Validate multiple workflows
$workflows = Workflow::where('tenant_id', $tenantId)->get();
$issues = [];

foreach ($workflows as $workflow) {
    $validation = $validator->validate(
        $workflow->draft_definition,
        $workflow,
        $actor
    );
    
    if (!$validation['is_publishable']) {
        $issues[$workflow->id] = $validation['errors'];
    }
}

return response()->json($issues);
```

---

## Performance Considerations

### Validation Cost

**Fast** (Syntax & Graph):
- No database queries
- In-memory operations
- ~1-5ms for typical workflows

**Slower** (Contextual):
- Multiple database queries
- One query per unique user reference
- One query per document reference
- ~50-200ms depending on references

### Optimization Tips

1. **Avoid unnecessary validation**: Skip contextual rule when not needed
   ```php
   // Skip context checks (null workflow)
   $validation = $validator->validate($definition, null, $user);
   ```

2. **Validate only changed sections**: 
   ```php
   // Don't re-validate unchanged parts
   // Implement incremental validation if needed
   ```

3. **Cache node definitions**:
   ```php
   // SyntaxVerificationRule loads Node definitions
   // Cache the query result if validating many times
   ```

4. **Batch context lookups**:
   ```php
   // If validating many workflows, batch load users
   // to reduce query count
   ```

### When to Run Each Stage

```php
// Real-time validation (user typing)
$validation = $validator->validate($definition, null, null);
// Syntax only, no context

// Draft save (auto-save)
$validation = $validator->validate($definition, $workflow, $user);
// All stages, includes expensive contextual checks

// Quick check (don't show all issues)
$result = $validator->validate($definition, null, null);
if ($result['is_publishable']) {
    // Good enough, do full check in background
}
```

---

## Error Handling Best Practices

### User-Friendly Error Messages

```php
// Don't show raw error codes to users
❌ "Code: graph.unreachable"

// Instead, map to user-friendly text
✓ "Node 'Review Order' can't be reached. Check the flow connections."
✓ "Make sure node is connected to the start."
```

### Highlighting Issues in UI

```php
// Use path and node_id for highlighting
issue.path       // "nodes[2].config.expression"
issue.node_id    // "approve_step"
issue.edge_id    // "edge_to_approve"

// Highlight the problem area in workflow editor
highlight(issue.node_id);
```

### Logging for Debugging

```php
// Log detailed validation info for troubleshooting
\Log::info('Workflow validation failed', [
    'workflow_id' => $workflow->id,
    'issue_count' => count($validation['issues']),
    'errors' => $validation['errors'],
    'actor' => auth()->user()->id,
]);
```

---

## Advanced Usage

### Custom Validation Wrapper

```php
class WorkflowValidator {
    public function validate(array $definition, Workflow $workflow, User $user)
    {
        $result = $this->validator->validate($definition, $workflow, $user);
        
        // Add custom transformations
        return [
            'is_valid' => $result['is_publishable'],
            'error_count' => $result['summary']['errors'],
            'warning_count' => $result['summary']['warnings'],
            'messages' => array_map(fn($issue) => $this->formatMessage($issue), 
                                   $result['issues']),
            'raw' => $result,
        ];
    }
}
```

### Validation with Fallbacks

```php
// Try strict validation
$strict = $validator->validate($definition, $workflow, $user);

if (!$strict['is_publishable']) {
    // Relax some checks
    $relaxed = $validator->validate($definition, null, null);
    
    // Show both results
    return [
        'strict_errors' => $strict['errors'],
        'relaxed_errors' => $relaxed['errors'],
        'suggestion' => 'Some issues require workflow context',
    ];
}
```

### Metrics & Monitoring

```php
// Track validation metrics
$validation = $validator->validate($definition, $workflow, $user);

metric('workflow.validation.errors', $validation['summary']['errors']);
metric('workflow.validation.warnings', $validation['summary']['warnings']);
metric('workflow.nodes', count($definition['nodes'] ?? []));
metric('workflow.edges', count($definition['edges'] ?? []));
```
