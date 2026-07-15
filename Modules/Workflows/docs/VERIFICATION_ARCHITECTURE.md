# Workflow Verification System - Architecture & Design

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                   WorkflowManagementService                      │
│  (updateDraft, publish - entry points for verification)          │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│              WorkflowDefinitionValidator                         │
│  (Public API - converts results to array format)                 │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│             WorkflowVerificationService                          │
│  (Core engine - orchestrates verification pipeline)              │
└──────────────────────────┬──────────────────────────────────────┘
                           │
          ┌────────────────┼────────────────┐
          ↓                ↓                ↓
    ┌──────────┐     ┌──────────┐     ┌──────────┐
    │Normalize │     │   Build  │     │ Aggregate│
    │Definition│     │  Graph   │     │ Results  │
    └─────┬────┘     └──────────┘     └──────────┘
          │
          ↓ (normalized definition + graph)
    ┌──────────────────────────────────────────┐
    │         Verification Rules Chain          │
    │  (Sequential execution, early exit on    │
    │   critical failures possible)             │
    ├──────────────────────────────────────────┤
    │ 1. SyntaxVerificationRule                │
    │    - Structure validation                │
    │    - Type checking                       │
    │    - Field presence                      │
    ├──────────────────────────────────────────┤
    │ 2. GraphControlFlowVerificationRule      │
    │    - Entry/terminal node validation      │
    │    - Reachability analysis               │
    │    - Cycle detection                     │
    │    - Parallel join validation            │
    ├──────────────────────────────────────────┤
    │ 3. ExpressionVerificationRule            │
    │    - Expression parsing                  │
    │    - Boolean type checking               │
    │    - Variable identification             │
    ├──────────────────────────────────────────┤
    │ 4. ContextualVerificationRule            │
    │    - User/team membership checks         │
    │    - Knowledge base document validation  │
    │    - Business rule verification          │
    └──────────────────────────────────────────┘
          │
          ↓
    ┌──────────────────────────────────────────┐
    │    WorkflowVerificationResult            │
    │    (Collection of WorkflowViolations)    │
    └──────────────────────────────────────────┘
          │
          ↓
    ┌──────────────────────────────────────────┐
    │        Converted to Array Format         │
    │    (for API response)                    │
    └──────────────────────────────────────────┘
```

## Core Classes

### WorkflowVerificationService

**Responsibility**: Orchestrate the verification pipeline

```php
class WorkflowVerificationService {
    public function verify(
        array $definition, 
        ?Workflow $workflow = null, 
        ?User $actor = null
    ): WorkflowVerificationResult
}
```

**Key Methods**:
- `verify()` - Main entry point, executes all rules
- `rules()` - Returns the ordered array of verification rules

**Dependency Injection**:
```
- WorkflowDefinitionNormalizer
- SyntaxVerificationRule
- GraphControlFlowVerificationRule
- ExpressionVerificationRule
- ContextualVerificationRule
```

### WorkflowDefinitionNormalizer

**Responsibility**: Convert raw input into standardized format

```php
class WorkflowDefinitionNormalizer {
    public function normalize(array $definition): array
}
```

**Normalization Process**:
1. **Trigger normalization**: Extract type and config
2. **Variables normalization**: Ensure array format
3. **Nodes normalization**: Standardize node structure
4. **Edges normalization**: Handle different edge property names
5. **Settings normalization**: Preserve settings configuration

**Output Structure**:
```php
[
    'trigger' => [
        'type' => string,
        'config' => array,
        '_raw' => array,  // Original trigger
    ],
    'variables' => array,
    'nodes' => [
        [
            'id' => string,
            'type' => string,
            'label' => string,
            'config' => array,
            'is_entry_point' => bool,
            'is_terminal' => bool,
            '_index' => int,           // Position in original
            '_invalid' => bool,        // Structural issues
            '_raw' => array,           // Original node
        ],
        // ... more nodes
    ],
    'edges' => [
        [
            'id' => string,
            'source_node_key' => string,
            'target_node_key' => string,
            'branch_type' => 'default'|'conditional'|'parallel',
            'condition_expression' => ?string,
            'is_default_branch' => bool,
            'parallel_strategy' => ?string,  // 'fork_join'|'fire_and_forget'
            'join_node_key' => ?string,
            'sort_order' => int,
            '_index' => int,
            '_invalid' => bool,
            '_raw' => array,
        ],
        // ... more edges
    ],
    'settings' => array,
    '_raw' => array,  // Original definition
]
```

### WorkflowDefinitionGraph

**Responsibility**: Build and provide queries against the workflow graph

```php
class WorkflowDefinitionGraph {
    public function __construct(array $definition)
}
```

**Graph Construction**:
- Indexes all valid nodes by ID
- Creates adjacency lists for outgoing edges
- Creates reverse adjacency lists for incoming edges
- Enables efficient reachability and cycle queries

**Key Query Methods**:

| Method | Returns | Purpose |
|--------|---------|---------|
| `nodeIds()` | `string[]` | All node identifiers |
| `nodes()` | `array[]` | All node definitions |
| `edges()` | `array[]` | All edge definitions |
| `hasNode(nodeId)` | `bool` | Check if node exists |
| `outgoing(nodeId)` | `array[]` | Edges leaving a node |
| `incoming(nodeId)` | `array[]` | Edges entering a node |
| `explicitEntryNodeIds()` | `string[]` | Nodes marked `is_entry_point: true` |
| `inferredEntryNodeIds()` | `string[]` | Nodes with no incoming edges |
| `terminalNodeIds()` | `string[]` | Terminal nodes or nodes with no outgoing |
| `reachableFrom(nodeId)` | `string[]` | All nodes reachable from starting node |
| `nodesThatCanReachAny(nodeIds[])` | `string[]` | All nodes that can reach any terminal |
| `invalidCycleEdges()` | `array[]` | Edges that create invalid cycles |

**Reachability Algorithm**:
```
reachableFrom(start):
  visited = {}
  stack = [start]
  
  while stack not empty:
    node = pop(stack)
    if visited[node]: continue
    visited[node] = true
    
    for each outgoing edge of node:
      if target not visited:
        push(stack, target)
  
  return keys(visited)
```

**Cycle Detection**:
Uses depth-first search with "visiting" tracking:
- Tracks nodes currently in the recursion stack
- When encountering a node in the "visiting" set, a cycle exists
- Excludes cycles that belong to explicit "loop" nodes
- Returns edges that create invalid cycles

### WorkflowVerificationResult

**Responsibility**: Collect and aggregate verification issues

```php
class WorkflowVerificationResult {
    public function add(WorkflowViolation $violation): void
    public function addError(code, message, path, nodeId, edgeId): void
    public function addWarning(code, message, path, nodeId, edgeId): void
    public function isPublishable(): bool
    public function toArray(): array
}
```

**Result Queries**:
- `issues()` - All violations
- `errorCount()` - Number of errors
- `warningCount()` - Number of warnings
- `errors()` - Error messages only
- `warnings()` - Warning messages only
- `isPublishable()` - `errorCount() === 0`

### WorkflowViolation

**Responsibility**: Represent a single validation issue

```php
class WorkflowViolation {
    public readonly string $severity;        // 'error' or 'warning'
    public readonly string $code;            // Machine identifier
    public readonly string $message;         // Human description
    public readonly ?string $path;           // JSON path to issue
    public readonly ?string $nodeId;         // Affected node ID
    public readonly ?string $edgeId;         // Affected edge ID
    
    public static function error(...): self
    public static function warning(...): self
    public function toArray(): array
}
```

**Error Codes Convention**:
```
{category}.{specific_issue}
- definition.*          - Top-level definition issues
- trigger.*             - Trigger-specific issues
- node.*                - Node structure issues
- edge.*                - Edge structure issues
- graph.*               - Graph connectivity issues
- expression.*          - Expression parsing issues
- context.*             - Contextual/business rule issues
- parallel.*            - Parallel execution issues
```

### VerificationRule Interface

**Responsibility**: Define the contract for verification rules

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

**Parameters**:
- `$definition` - Normalized workflow definition
- `$graph` - Built workflow graph for queries
- `$result` - Mutable result object to collect violations
- `$workflow` - Context workflow model (may be null)
- `$actor` - Acting user for authorization checks

## Data Flow Example

```php
// Input: Raw workflow definition
$definition = [
    'trigger' => ['type' => 'webhook-trigger', 'config' => [...]],
    'nodes' => [
        ['id' => 'n1', 'type' => 'start', 'is_entry_point' => true],
        ['id' => 'n2', 'type' => 'condition', 'config' => ['expression' => 'x > 5']],
    ],
    'edges' => [
        ['source' => 'n1', 'to' => 'n2'],  // Note: alternate property names
    ],
];

// Normalization:
// - 'to' → 'target_node_key'
// - 'source' → 'source_node_key'
// - Missing properties filled with defaults
// - Original preserved in '_raw'

// Graph construction:
// - Builds node index: {'n1' => ..., 'n2' => ...}
// - Creates adjacency: n1 → [edge_to_n2], n2 → []

// Verification:
// - Syntax: Checks all required fields, types
// - Graph: Validates reachability, entry/terminal nodes
// - Expression: Parses 'x > 5' for boolean expression
// - Contextual: Validates users, documents, etc.

// Result:
[
    'is_publishable' => true,
    'summary' => ['errors' => 0, 'warnings' => 0],
    'issues' => [],
    'errors' => [],
    'warnings' => [],
]
```

## Extension Points

### Adding a New Verification Rule

1. Create new class implementing `VerificationRule`
2. Register in service provider (DI container)
3. Add to `WorkflowVerificationService::rules()` in desired order

```php
// MyCustomRule.php
class MyCustomRule implements VerificationRule {
    public function verify(array $definition, WorkflowDefinitionGraph $graph, 
                          WorkflowVerificationResult $result, ?Workflow $workflow = null, 
                          ?User $actor = null): void {
        // Your verification logic
        if ($someInvalidCondition) {
            $result->addError('custom.issue', 'Description', 'path.to.field');
        }
    }
}

// In WorkflowsServiceProvider
$this->app->singleton(MyCustomRule::class);

// In WorkflowVerificationService::rules()
return [
    $this->syntaxRule,
    $this->graphRule,
    $this->expressionRule,
    $this->contextualRule,
    $this->myCustomRule,  // ← Add here
];
```

### Accessing Context Information

Rules can use the `$workflow` and `$actor` parameters for context:

```php
// Check team membership
if ($workflow !== null) {
    $teamId = $workflow->team_id;
    $tenantId = $workflow->tenant_id;
}

// Check actor authorization
if ($actor !== null) {
    $canModify = $actor->role === Role::Manager;
}
```
