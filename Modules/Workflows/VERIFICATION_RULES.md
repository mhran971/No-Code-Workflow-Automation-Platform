# Workflow Verification Rules - Detailed Reference

## Overview

This document describes each verification rule in detail: what it validates, what errors it produces, and how it works.

## Validation API

`POST /api/v1/workflows/validate`

Request body:

```json
{
  "definition": {
    "trigger": {
      "type": "webhook-trigger",
      "config": {
        "webhookUrl": "https://example.test/workflows/employee-onboarding"
      }
    },
    "nodes": [
      {
        "id": "start",
        "type": "send-email",
        "label": "Send welcome email",
        "config": {
          "from": "hr@example.test",
          "to": "employee@example.test",
          "subject": "Welcome aboard"
        },
        "is_entry_point": true,
        "is_terminal": true
      }
    ],
    "edges": [],
    "variables": [],
    "settings": {}
  }
}
```

Response:

- `request_schema` describes the definition shape the frontend should send
- `example_definition` is a ready-to-edit sample payload
- `response_schema` describes the validation result shape
- `is_publishable`
- `summary.errors`
- `summary.warnings`
- `issues[]` with `location.path`, `location.field`, `location.scope`, `location.node_id`, and `location.edge_id`
- `errors[]`
- `warnings[]`

The request format is enforced by `ValidateWorkflowDefinitionRequest`, and the response is serialized by `WorkflowValidationResultResource`.

---

## 1. SyntaxVerificationRule

**File**: `Modules/Workflows/app/Services/Verification/Rules/SyntaxVerificationRule.php`

**Purpose**: Validate the structural integrity and basic consistency of the workflow definition.

**Execution Order**: First (earliest)

**Dependencies**:

- Node definitions from database (to validate node/trigger types exist and are active)

### What It Validates

#### A. Top-Level Shape

Ensures the definition contains required keys and correct types:

```
✓ 'trigger' key exists and is an object
✓ 'nodes' key exists and is an array
✓ 'edges' key exists and is an array
✓ 'variables' key (if present) is an array
✓ 'settings' key (if present) is an object
```

**Example Errors**:

- `definition.trigger_missing` - "Workflow trigger must be configured."
- `definition.nodes_invalid` - "Workflow nodes must be an array."

#### B. Trigger Validation

Validates the workflow trigger configuration:

```
✓ Trigger type is non-empty string
✓ Trigger type exists in active Node definitions
✓ Trigger config is an object
✓ Trigger config fields match Node definition requirements
```

**Error Codes**:

- `trigger.missing` - Trigger not provided at all
- `trigger.type_missing` - Type field empty or missing
- `trigger.type_unknown` - Type doesn't exist or isn't active
- `trigger.config_invalid` - Config is not an object

#### C. Node Validation

Validates each node in the workflow:

```
✓ Each node is an object (not array, string, etc.)
✓ Node ID is non-empty string
✓ Node IDs are unique across all nodes
✓ Node type is non-empty string
✓ Node type exists in active Node definitions
✓ Node config is an object
✓ Node config fields match Node definition requirements
```

**Error Codes**:

- `node.invalid` - Node is not an object
- `node.id_missing` - ID is empty or missing
- `node.id_duplicate` - ID already used by another node
- `node.type_missing` - Type is empty or missing
- `node.type_unknown` - Type doesn't exist or isn't active
- `node.config_invalid` - Config is not an object

#### D. Edge Validation

Validates each edge (connection) between nodes:

```
✓ Each edge is an object
✓ Source node ID is non-empty
✓ Source node exists in workflow
✓ Target node ID is non-empty
✓ Target node exists in workflow
✓ No duplicate edges (same source→target pair)
✓ Branch type is valid: 'default', 'conditional', or 'parallel'
```

**Error Codes**:

- `edge.invalid` - Edge is not an object
- `edge.source_missing` - Source node ID not provided
- `edge.source_missing_reference` - Source node doesn't exist
- `edge.target_missing` - Target node ID not provided
- `edge.target_missing_reference` - Target node doesn't exist
- `edge.duplicate` - Duplicate edge to same target
- `edge.branch_type_invalid` - Invalid branch type

### Config Field Validation

When validating trigger/node config fields, SyntaxVerificationRule checks that:

1. All required fields are present
2. Field values match expected types
3. Enum values are valid
4. Numeric ranges are respected

### Example Violations

```
Path: 'nodes[0].id'
Error: 'node.id_duplicate'
Message: "Workflow node id 'start' is duplicated."
Node ID: 'start'

---

Path: 'edges[2].target_node_key'
Error: 'edge.target_missing_reference'
Message: "Edge target node does not exist."
Node ID: 'node_a'
Edge ID: 'edge-3'

---

Path: 'trigger.type'
Error: 'trigger.type_unknown'
Message: "Workflow trigger type 'custom-webhook' is not active or does not exist."
```

---

## 2. GraphControlFlowVerificationRule

**File**: `Modules/Workflows/app/Services/Verification/Rules/GraphControlFlowVerificationRule.php`

**Purpose**: Validate the logical flow and connectivity of the workflow graph.

**Execution Order**: Second

**Dependencies**:

- `WorkflowDefinitionGraph` (already built)

### What It Validates

#### A. Entry Node Resolution

Determines and validates the workflow entry point:

**Steps**:

1. Check for explicit entry nodes (marked `is_entry_point: true`)
2. If exactly one: use it
3. If multiple: error - "must have exactly one entry node"
4. If none: infer from nodes with no incoming edges
5. If inferred count ≠ 1: error - ambiguous or missing entry

**Error Codes**:

- `graph.entry_missing` - No entry point found
- `graph.entry_ambiguous` - Multiple nodes without incoming edges

#### B. Terminal Node Validation

Ensures workflow has at least one end point:

```
✓ Terminal nodes are marked explicitly (is_terminal: true)
  OR
✓ Nodes with no outgoing edges
```

**Error Codes**:

- `graph.terminal_missing` - No terminal nodes exist

#### C. Node Degree Analysis

For each node, validates incoming and outgoing connections:

```
Rule 1: Non-entry nodes must have incoming edges
  ✓ Every node except entry must have ≥1 incoming edge

Rule 2: Non-terminal nodes must have outgoing edges
  ✓ Every node except terminals must have ≥1 outgoing edge
```

**Error Codes**:

- `graph.incoming_missing` - "Node 'X' must have at least one incoming edge."
- `graph.outgoing_missing` - "Node 'X' must have at least one outgoing edge."

#### D. Reachability Analysis

Two-directional reachability checks using depth-first search:

**Forward Reachability**:

- Starting from entry node, visit all nodes following outgoing edges
- All nodes must be reachable
- If not: node is "dead code"

**Backward Reachability**:

- Starting from terminal nodes, visit all nodes following incoming edges
- All nodes must be able to reach at least one terminal
- If not: node is a "dead end"

**Error Codes**:

- `graph.unreachable` - "Node 'X' is not reachable from the entry node."
- `graph.dead_end` - "Node 'X' is not on a path to a terminal node."

**Example**:

```
Entry: [A] → B → [End]
           ↓
           C (unreachable from A)

Entry: [A] → B
       ↓
       C → D → [End]
       
       D is isolated
```

#### E. Cycle Detection

Detects cycles using depth-first search with "visiting" tracking:

**Algorithm**:

1. Maintain `visited` and `visiting` sets
2. For each node, recursively visit outgoing nodes
3. If we encounter a node in `visiting`: cycle detected
4. Exception: cycles allowed if source OR target is "loop" node type

**Error Codes**:

- `graph.unstructured_cycle` - "Unstructured cycle detected from 'X' to 'Y'."

**Valid Cycles** (allowed):

- Cycles involving explicit "loop" nodes (designed for iterations)
- These are considered structured loops

**Invalid Cycles** (errors):

- Cycles between regular nodes without loop structure
- Can cause infinite execution

**Example**:

```
Valid:
  A → [loop] → B → A  (structured loop)

Invalid:
  A → B → A           (unstructured cycle)
```

#### F. Parallel Join Metadata Validation

For parallel execution edges, validates join node configuration:

```
Parallel Edge Types:
  - 'fire_and_forget' - No join needed
  - 'fork_join' - Requires join_node_key

Validation:
✓ fork_join edges must declare join_node_key
✓ join_node_key must reference existing node
✓ join node type must be 'parallel-join'
```

**Error Codes**:

- `parallel.join_missing` - "Parallel fork/join edge must declare join_node_key."
- `parallel.join_unknown` - "Parallel join node 'X' does not exist."
- `parallel.join_type_invalid` - "Parallel join node 'X' must be a parallel-join node."

### Graph Query Methods Used

```php
$graph->explicitEntryNodeIds()      // Nodes marked is_entry_point: true
$graph->inferredEntryNodeIds()      // Nodes with no incoming edges
$graph->terminalNodeIds()            // Terminal or no-outgoing nodes
$graph->incoming(nodeId)             // Incoming edges
$graph->outgoing(nodeId)             // Outgoing edges
$graph->reachableFrom(entryId)      // All nodes reachable from entry
$graph->nodesThatCanReachAny([...]) // Nodes that can reach terminals
$graph->invalidCycleEdges()         // Edges creating invalid cycles
$graph->nodeType(nodeId)             // Get node type for checking
$graph->hasNode(nodeId)              // Check if node exists
```

---

## 3. ExpressionVerificationRule

**File**: `Modules/Workflows/app/Services/Verification/Rules/ExpressionVerificationRule.php`

**Purpose**: Validate conditional expressions and boolean logic in workflows.

**Execution Order**: Third

**Dependencies**:

- `ExpressionLanguageValidator`

### What It Validates

#### A. Edge Conditional Expressions

For edges with `branch_type: 'conditional'`:

```
✓ Condition expression is non-empty string
✓ Expression is valid boolean-evaluating syntax
✓ Expression contains no undefined variables (warnings)
```

**Error Codes**:

- `expression.condition_missing` - Edge missing condition_expression
- `expression.condition_invalid` - Expression parse error

#### B. Node Expressions

For node config containing 'expression' or 'condition' fields:

```
✓ Expression is valid syntax
✓ Expression is boolean-evaluating
```

**Error Codes**:

- `expression.node_invalid` - Node expression parse error

### Expression Language Specification

**Supported Operators**:

```
Logical:  &&  (AND),  ||  (OR),  !  (NOT)
Comparison: ==, !=, <, >, <=, >=
Grouping: ()
```

**Supported Types**:

```
Literals:     true, false, null
Numbers:      42, 3.14, -5, 0
Strings:      "text", 'text'
Identifiers:  variable_name, nested.property
```

**Type Rules**:

```
- Both sides of && and || must be boolean-like
- Comparisons produce boolean
- Variables are assumed boolean (if used in boolean context)
```

### ExpressionLanguageValidator Details

**Tokenizer**:

- Handles whitespace
- Recognizes operators: `&&`, `||`, `==`, `!=`, `>=`, `<=`, `>`, `<`, `!`
- Parses strings (single/double quoted)
- Parses numbers (integers, decimals, negative)
- Parses identifiers and property access

**Parser**:
Recursive descent parser with precedence:

```
Expression → OR-Expression
OR-Expression → AND-Expression (|| AND-Expression)*
AND-Expression → Comparison (&& Comparison)*
Comparison → Unary ((==|!=|<|>|<=|>=) Unary)*
Unary → ! Unary | Primary
Primary → NUMBER | STRING | IDENTIFIER | ( Expression )
```

**Validation Output**:

```php
ExpressionValidationResult {
    public bool $valid;           // Syntax valid?
    public bool $isBoolean;       // Evaluates to boolean?
    public array $variables;      // Identifiers found
    public ?string $message;      // Error message if invalid
}
```

### Example Violations

```
Expression: "x >"
Error: 'expression.condition_invalid'
Message: "Conditional edge expression is invalid: Unexpected end of input."

---

Expression: "status == 'active' && user_id > 0"
Variables: ['status', 'user_id']
Valid: true
IsBoolean: true

---

Expression: "x + 5"
Error: 'expression.condition_invalid'
Message: "Expression must evaluate to a boolean value."
(arithmetic operators not supported)
```

---

## 4. ContextualVerificationRule

**File**: `Modules/Workflows/app/Services/Verification/Rules/ContextualVerificationRule.php`

**Purpose**: Validate references to real-world entities and business rules.

**Execution Order**: Fourth (last)

**Dependencies**:

- Database access (User, TeamMembership, Document models)
- `$workflow` context required (skipped if null)

### What It Validates

#### A. Human Task Assignees

For nodes with type `'human-task'`:

```
Validation Chain:
1. Check if assignee field is numeric ID
2. User with that ID exists
3. User is in same tenant
4. User is active (is_active: true)
5. User is member of workflow's team
6. Team membership status is 'active'
```

**Error Codes**:

- `context.assignee_unknown` - User doesn't exist or is inactive
- `context.assignee_not_team_member` - User not on workflow team

**Example**:

```
Node Config: { assignee: 42 }

Checks:
✓ User 42 exists
✓ User 42 belongs to workflow tenant
✓ User 42 is active
✗ User 42 not member of team "Sales Team"
  → Error: "Human task assignee '42' must be an active member 
           of the workflow team."
```

#### B. Knowledge Base Documents

For nodes starting with `'ai-'` (AI nodes):

```
Validation Chain:
1. Check if kbDocs config field exists
2. If exists, must be array of numeric IDs
3. For each ID:
   a. Document with that ID exists
   b. Document belongs to same tenant
```

**Error Codes**:

- `context.kb_docs_invalid` - kbDocs is not an array
- `context.kb_doc_id_invalid` - Array item is not numeric ID
- `context.kb_doc_unknown` - Document doesn't exist in tenant

**Example**:

```
Node Config: { kbDocs: [1, 2, 3] }

Checks:
✓ Document 1 exists in tenant
✓ Document 2 exists in tenant
✗ Document 3 not found
  → Error: "Knowledge Base document '3' does not exist 
           for this tenant."
```

### Database Queries

**Assignee Validation**:

```php
// Check user exists and is active
User::where('id', $assigneeId)
    ->where('tenant_id', $tenantId)
    ->where('is_active', true)
    ->exists()

// Check team membership
TeamMembership::where('tenant_id', $tenantId)
    ->where('team_id', $teamId)
    ->where('user_id', $assigneeId)
    ->where('status', 'active')
    ->exists()
```

**Document Validation**:

```php
// Check document exists
Document::where('id', $docId)
    ->where('tenant_id', $tenantId)
    ->exists()
```

### Context Requirements

This rule **requires** the `$workflow` parameter:

- Contains `tenant_id` for cross-tenant isolation
- Contains `team_id` for team membership validation

If `$workflow` is null, this rule is skipped entirely.

---

## Error Code Reference

Complete list of all error codes by rule:

### Syntax Errors

```
definition.trigger_missing
definition.trigger_invalid
definition.nodes_missing
definition.nodes_invalid
definition.edges_missing
definition.edges_invalid
definition.variables_invalid
definition.settings_invalid

trigger.missing
trigger.type_missing
trigger.type_unknown
trigger.config_invalid

node.invalid
node.id_missing
node.id_duplicate
node.type_missing
node.type_unknown
node.config_invalid
node.{fieldName}_invalid          (for config fields)
node.{fieldName}_missing          (for config fields)

edge.invalid
edge.source_missing
edge.source_missing_reference
edge.target_missing
edge.target_missing_reference
edge.duplicate
edge.branch_type_invalid
```

### Graph Control Flow Errors

```
graph.entry_missing
graph.entry_ambiguous
graph.entry_multiple
graph.terminal_missing
graph.incoming_missing
graph.outgoing_missing
graph.unreachable
graph.dead_end
graph.unstructured_cycle

parallel.join_missing
parallel.join_unknown
parallel.join_type_invalid
```

### Expression Errors

```
expression.condition_missing
expression.condition_invalid
expression.node_invalid
```

### Contextual Errors

```
context.assignee_unknown
context.assignee_not_team_member

context.kb_docs_invalid
context.kb_doc_id_invalid
context.kb_doc_unknown
```

---

## Rule Execution Order & Implications

The sequential execution is important:

1. **Syntax** runs first
   - If syntax fails, graph can't be built safely
   - Later rules skip if fundamental structure is broken

2. **Graph Control Flow** runs second
   - Uses the graph built after syntax validation
   - Validates logical connectivity

3. **Expression** runs third
   - Validates syntax of string expressions
   - Independent of graph structure

4. **Contextual** runs last
   - Database queries (most expensive)
   - Only runs if earlier validation passes
   - Only runs if `$workflow` provided

**Performance Implication**: Database lookups only happen for syntactically valid workflows in context of a real workflow model.
