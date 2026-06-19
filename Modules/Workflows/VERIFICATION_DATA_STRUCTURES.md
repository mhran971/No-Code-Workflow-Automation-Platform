# Workflow Verification - Data Structures & Models

## Overview

This document details all data structures used in the workflow verification system, including their properties, relationships, and usage patterns.

---

## Raw Workflow Definition (Input)

### Structure

```php
// User-provided workflow definition
[
    'trigger' => [...],      // Required: Trigger configuration
    'nodes' => [...],        // Required: Array of nodes
    'edges' => [...],        // Required: Array of edges
    'variables' => [...],    // Optional: Workflow variables
    'settings' => [...],     // Optional: Workflow settings
]
```

### Trigger Object

```php
[
    'type' => string,           // Required: trigger type
    'config' => array,          // Required: trigger-specific config
]

// Example
[
    'type' => 'webhook-trigger',
    'config' => [
        'webhookUrl' => 'https://example.com/webhook',
        'method' => 'POST',
        'authentication' => 'bearer_token',
    ],
]
```

### Node Object

```php
[
    'id' => string,                    // Required: unique identifier
    'type' => string,                  // Required: node type (must exist)
    'label' => string,                 // Optional: display name
    'config' => array,                 // Required: node-specific configuration
    'is_entry_point' => bool,          // Optional: marks workflow entry
    'is_terminal' => bool,             // Optional: marks workflow end
]

// Example
[
    'id' => 'review_approval',
    'type' => 'human-task',
    'label' => 'Manager Review',
    'config' => [
        'taskName' => 'Approve Purchase Request',
        'assignee' => 42,               // User ID
        'priority' => 'high',
        'dueIn' => 3,                   // days
    ],
    'is_entry_point' => false,
    'is_terminal' => false,
]
```

### Edge Object

```php
[
    'id' => string,                           // Optional: edge identifier
    'source' => string,                       // Source node ID (alt: source_node_key)
    'target' => string,                       // Target node ID (alt: target_node_key, to)
    'branch_type' => 'default'|'conditional'|'parallel',  // Optional
    'condition_expression' => string,         // Required if conditional
    'is_default_branch' => bool,              // Optional: default path
    'parallel_strategy' => string,            // 'fork_join'|'fire_and_forget'
    'join_node_key' => string,                // Required if fork_join
    'sort_order' => int,                      // Optional: execution order
]

// Example: Simple edge
[
    'source' => 'start',
    'target' => 'review_approval',
]

// Example: Conditional edge
[
    'source' => 'review_approval',
    'target' => 'approved_process',
    'branch_type' => 'conditional',
    'condition_expression' => 'approval_status == "approved"',
    'is_default_branch' => false,
]

// Example: Parallel fork
[
    'source' => 'split_work',
    'target' => 'task_a',
    'branch_type' => 'parallel',
    'parallel_strategy' => 'fork_join',
    'join_node_key' => 'join_results',
]
```

### Variables Array

```php
[
    [
        'name' => 'approval_timeout',
        'type' => 'integer',
        'default' => 3600,
    ],
    [
        'name' => 'request_data',
        'type' => 'object',
    ],
]
```

### Settings Object

```php
[
    'retry_policy' => [
        'max_retries' => 3,
        'backoff_strategy' => 'exponential',
    ],
    'timeout' => [
        'execution' => 3600,
        'task' => 1800,
    ],
    'notifications' => [
        'on_start' => true,
        'on_complete' => true,
        'on_error' => true,
    ],
]
```

---

## Normalized Workflow Definition (Internal)

### Structure

Output from `WorkflowDefinitionNormalizer`:

```php
[
    'trigger' => [
        'type' => string,
        'config' => array,
        '_raw' => array,           // Original trigger
    ],
    'variables' => array,          // Normalized to array (guaranteed)
    'nodes' => [...],              // Array of normalized node objects
    'edges' => [...],              // Array of normalized edge objects
    'settings' => array,           // Normalized to array (guaranteed)
    '_raw' => array,               // Original definition
]
```

### Normalized Node

```php
[
    'id' => string|null,              // Extracted from 'id' or 'key'
    'type' => string|null,            // Node type
    'label' => string|null,           // Display name
    'config' => array,                // Normalized config (always array)
    'is_entry_point' => bool,         // Coerced to boolean
    'is_terminal' => bool,            // Coerced to boolean
    '_index' => int,                  // Original position in array
    '_invalid' => bool,               // true if node wasn't an object
    '_raw' => mixed,                  // Original node value
]
```

### Normalized Edge

```php
[
    'id' => string,                   // Generated if missing
    'source_node_key' => string|null, // Resolved from various property names
    'target_node_key' => string|null, // Resolved from various property names
    'branch_type' => string,          // Normalized, defaults to 'default'
    'condition_expression' => string|null,  // Extracted from 'condition' or 'condition_expression'
    'is_default_branch' => bool,      // Coerced to boolean
    'parallel_strategy' => string|null,    // Optional parallel strategy
    'join_node_key' => string|null,   // Join node reference
    'sort_order' => int,              // Defaults to 0
    '_index' => int,                  // Original position
    '_invalid' => bool,               // true if edge wasn't an object
    '_raw' => mixed,                  // Original edge value
]
```

### Normalizer's Flexibility

The normalizer accepts multiple property name formats for compatibility:

```php
// These all work for source node:
'source'           // ← Accepted
'source_node_key'  // ← Normalized to this
'from'             // ← Accepted

// These all work for target node:
'target'           // ← Accepted
'target_node_key'  // ← Normalized to this
'to'               // ← Accepted

// These all work for condition:
'condition'        // ← Accepted
'condition_expression'  // ← Normalized to this
```

---

## WorkflowDefinitionGraph (In-Memory Structure)

### Construction

```php
new WorkflowDefinitionGraph($normalizedDefinition)
```

### Internal Representation

```php
class WorkflowDefinitionGraph {
    protected array $definition;          // Normalized definition
    
    // Node lookups
    protected array $nodesById = [        // 'node_id' => node_data
        'start' => [...],
        'process' => [...],
    ];
    
    // Adjacency lists
    protected array $outgoing = [         // 'node_id' => [edges leaving this node]
        'start' => [edge1, edge2],
        'process' => [edge3],
    ];
    
    protected array $incoming = [         // 'node_id' => [edges entering this node]
        'start' => [],
        'process' => [edge1],
    ];
}
```

### Query Methods

#### Node Information

```php
// Check if node exists
$bool = $graph->hasNode('node_id');

// Get node object
$nodeData = $graph->node('node_id');  // or null

// Get node type
$type = $graph->nodeType('node_id');  // or null

// Get all node IDs
$ids = $graph->nodeIds();             // ['start', 'process', ...]

// Get all node objects
$nodes = $graph->nodes();             // [node1, node2, ...]
```

#### Edge Information

```php
// Get all edges
$edges = $graph->edges();

// Get edges leaving node
$outgoing = $graph->outgoing('node_id');  // []

// Get edges entering node
$incoming = $graph->incoming('node_id');  // []
```

#### Entry Point Detection

```php
// Nodes explicitly marked
$explicit = $graph->explicitEntryNodeIds();  // ['start']

// Nodes with no incoming edges
$inferred = $graph->inferredEntryNodeIds();  // ['node_x']
```

#### Terminal Point Detection

```php
// Terminal nodes
$terminals = $graph->terminalNodeIds();  // ['end', 'error_end']
```

#### Reachability Analysis

```php
// All nodes reachable from starting node
$reachable = $graph->reachableFrom('start');
// ['start', 'process', 'end']

// All nodes that can reach any terminal
$canReachTerminal = $graph->nodesThatCanReachAny(['end', 'error_end']);
// ['start', 'process', 'end', 'error_end']
```

#### Cycle Detection

```php
// Edges that form invalid cycles
$invalidEdges = $graph->invalidCycleEdges();
// [
//     ['source' => 'step_a', 'target' => 'step_b', 'edge_id' => 'e1'],
// ]
```

---

## WorkflowViolation (Issue Object)

### Structure

```php
class WorkflowViolation {
    public readonly string $severity;    // 'error' or 'warning'
    public readonly string $code;        // 'node.id_duplicate', etc.
    public readonly string $message;     // "Node 'start' has duplicate ID"
    public readonly ?string $path;       // 'nodes[0].id'
    public readonly ?string $nodeId;     // 'start'
    public readonly ?string $edgeId;     // 'edge_1'
}
```

### Factory Methods

```php
// Create error violation
WorkflowViolation::error(
    code: 'node.id_duplicate',
    message: "Node 'start' has duplicate ID",
    path: 'nodes[0].id',
    nodeId: 'start',
    edgeId: null
);

// Create warning violation
WorkflowViolation::warning(
    code: 'graph.dead_end',
    message: "Node 'old_step' doesn't lead to any terminal",
    path: null,
    nodeId: 'old_step',
    edgeId: null
);
```

### Array Representation

```php
$violation->toArray();
// [
//     'severity' => 'error',
//     'code' => 'node.id_duplicate',
//     'message' => "Node 'start' has duplicate ID",
//     'path' => 'nodes[0].id',
//     'node_id' => 'start',
//     'edge_id' => null,
// ]
```

---

## WorkflowVerificationResult (Results Container)

### Structure

```php
class WorkflowVerificationResult {
    protected array $issues = [];  // WorkflowViolation[]
}
```

### Adding Violations

```php
// Add pre-built violation
$result->add($violation);

// Add error (shorthand)
$result->addError(
    code: 'node.type_unknown',
    message: "Type 'my-node' doesn't exist",
    path: 'nodes[0].type',
    nodeId: 'node_1',
    edgeId: null
);

// Add warning (shorthand)
$result->addWarning(
    code: 'graph.dead_end',
    message: "Node can't reach terminal",
    path: null,
    nodeId: 'orphan_node'
);
```

### Querying Results

```php
// Get all violations
$issues = $result->issues();          // WorkflowViolation[]

// Count issues
$errorCount = $result->errorCount();       // int
$warningCount = $result->warningCount();   // int

// Check if publishable
$publishable = $result->isPublishable();   // bool (errorCount === 0)

// Get messages only
$errorMessages = $result->errors();        // string[]
$warningMessages = $result->warnings();    // string[]
```

### Array Conversion

```php
$result->toArray();
// [
//     'is_publishable' => true|false,
//     'summary' => [
//         'errors' => 0,
//         'warnings' => 0,
//     ],
//     'issues' => [
//         ['severity' => 'error', ...],
//         ...
//     ],
//     'errors' => ['Error message 1', ...],
//     'warnings' => ['Warning message 1', ...],
// ]
```

---

## ExpressionValidationResult

### Structure

```php
class ExpressionValidationResult {
    public bool $valid;              // Syntax is valid
    public bool $isBoolean;          // Evaluates to boolean
    public array $variables;         // ['var1', 'var2']
    public ?string $message;         // Error message if invalid
}
```

### Constructor

```php
new ExpressionValidationResult(
    valid: true,
    isBoolean: true,
    variables: ['status', 'user_id'],
    message: null
);
```

### Usage Example

```php
$validator = new ExpressionLanguageValidator();
$result = $validator->validateBoolean('status == "active" && user_id > 0');

if ($result->valid && $result->isBoolean) {
    // Expression is valid and boolean
    $variables = $result->variables;  // ['status', 'user_id']
} else {
    // Show error
    echo $result->message;
}
```

---

## Verification Rule Interface

### Contract

```php
interface VerificationRule {
    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
    ): void;
}
```

### Parameters Explained

| Parameter | Type | Required? | Purpose |
|-----------|------|-----------|---------|
| `$definition` | array | Yes | Normalized workflow definition |
| `$graph` | WorkflowDefinitionGraph | Yes | Built graph structure |
| `$result` | WorkflowVerificationResult | Yes | Mutable result to add violations |
| `$workflow` | Workflow\|null | No | Context workflow model |
| `$actor` | User\|null | No | User performing action |

### Return Value

Rules return `void`. Violations are added via `$result->add*()` methods.

---

## Workflow Model Integration

### Relevant Properties

```php
class Workflow {
    public int $id;
    public int $tenant_id;
    public int $team_id;
    public int $created_by_id;
    
    public string $name;
    public ?string $description;
    
    public string $status;              // 'active', 'disabled', 'deleted'
    public array $draft_definition;     // Raw definition
    public int $draft_revision;         // Draft version number
    
    public ?int $current_version_id;    // Published version ID
    public int $current_version_number; // Published version number
    public ?string $current_version_label;  // e.g., 'v1.0.0'
    
    public int $total_runs;
    public int $active_instances;
    
    public timestamps;
}
```

### Using in Verification

```php
// Verification needs workflow context for:
$tenantId = $workflow->tenant_id;       // Cross-tenant isolation
$teamId = $workflow->team_id;           // Team membership checks
$workflowId = $workflow->id;            // Reference for errors

// Can access relationships:
$team = $workflow->team;                // Team model
$creator = $workflow->createdBy;        // User model
$versions = $workflow->versions();      // Versions collection
```

---

## Node Definition Model

### Database Structure

```php
class Node {
    public int $id;
    public string $type;              // Unique identifier
    public string $name;              // Display name
    public ?string $description;
    public string $category;          // 'trigger', 'action', 'logic'
    public bool $is_active;           // Whether usable in workflows
    
    public hasMany $configFields;     // NodeConfigField[]
}
```

### NodeConfigField Structure

```php
class NodeConfigField {
    public int $id;
    public int $node_id;
    
    public string $name;              // Field key
    public string $type;              // NodeConfigFieldType enum
    public string $label;             // Display name
    public ?string $description;
    
    public bool $is_required;         // Must be present
    public mixed $default_value;      // Default if not provided
    
    public array $metadata;           // Type-specific options
                                      // (enum values, ranges, etc.)
}
```

### NodeConfigFieldType Enum

```php
enum NodeConfigFieldType: string {
    case STRING = 'string';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case ENUM = 'enum';
    case DATE = 'date';
}
```

---

## User & TeamMembership Models

### User Model

```php
class User {
    public int $id;
    public int $tenant_id;
    public string $email;
    public string $name;
    public string $first_name;
    public string $last_name;
    
    public Role $role;                // Enum: Manager, Employee, BusinessOwner
    public bool $is_active;           // Active users only
    
    public timestamps;
}
```

### TeamMembership Model

```php
class TeamMembership {
    public int $id;
    public int $tenant_id;
    public int $team_id;
    public int $user_id;
    
    public string $status;            // 'active', 'inactive', etc.
    
    public belongsTo User;
    public belongsTo Team;
    
    public timestamps;
}
```

### Verification Usage

```php
// Check if user can be assigned task
$exists = User::where('id', $userId)
    ->where('tenant_id', $tenantId)
    ->where('is_active', true)
    ->exists();

// Check team membership
$isMember = TeamMembership::where('team_id', $teamId)
    ->where('user_id', $userId)
    ->where('status', 'active')
    ->exists();
```

---

## Document Model (Knowledge Base)

### Structure

```php
class Document {
    public int $id;
    public int $tenant_id;
    
    public string $title;
    public string $content;
    public string $status;           // 'draft', 'published'
    
    public timestamps;
}
```

### Verification Usage

```php
// Check if document exists
$exists = Document::where('id', $docId)
    ->where('tenant_id', $tenantId)
    ->exists();
```

---

## Validation Errors Object (API Response)

### Structure

```php
[
    'is_publishable' => true|false,
    'summary' => [
        'errors' => 3,
        'warnings' => 1,
    ],
    'issues' => [
        [
            'severity' => 'error',
            'code' => 'syntax.trigger_missing',
            'message' => 'Workflow trigger must be configured.',
            'path' => 'trigger',
            'node_id' => null,
            'edge_id' => null,
        ],
        // ... more issues
    ],
    'errors' => [
        'Workflow trigger must be configured.',
        'Node with ID "n1" is not connected to workflow',
    ],
    'warnings' => [
        'Node "n3" is unreachable from entry point',
    ],
]
```

### HTTP Response Example

```php
// 422 Unprocessable Entity (has errors)
response()->json($validation, 422);

// 200 OK (no errors, may have warnings)
response()->json($validation, 200);
```

---

## Path Notation

Paths help locate issues in the definition:

```
'trigger'                    // Top-level trigger
'trigger.type'              // Trigger type field
'trigger.config'            // Trigger config
'trigger.config.webhookUrl' // Specific config field

'nodes'                     // All nodes array
'nodes[0]'                  // First node
'nodes[0].id'              // First node's ID
'nodes[0].config'          // First node's config
'nodes[0].config.assignee' // Specific config field

'edges'                     // All edges array
'edges[2]'                 // Third edge
'edges[2].source_node_key' // Third edge's source

'variables'                // All variables
'variables[1].name'        // Second variable's name
```

### Using Paths in UI

```javascript
// Parse path to highlight elements
function highlightError(path) {
    if (path?.startsWith('nodes[')) {
        const match = path.match(/nodes\[(\d+)\]/);
        const nodeIndex = parseInt(match[1]);
        highlightNode(nodeIndex);
    } else if (path?.startsWith('edges[')) {
        const match = path.match(/edges\[(\d+)\]/);
        const edgeIndex = parseInt(match[1]);
        highlightEdge(edgeIndex);
    }
}
```
