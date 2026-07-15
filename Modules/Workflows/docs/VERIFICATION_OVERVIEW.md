# Workflow Verification System - High Level Overview

## Purpose

The Workflow Verification System ensures that workflow definitions are valid, consistent, and executable before they are published to production. It provides a comprehensive, multi-stage validation pipeline that checks workflows at four different levels:

1. **Syntax verification** - Structure and data type validation
2. **Graph control flow verification** - Logical flow and reachability analysis
3. **Expression verification** - Boolean expression parsing and validation
4. **Contextual verification** - Business rule and context-dependent checks

## Key Components

### 1. **WorkflowManagementService**
The main orchestrator for workflow operations. It integrates the verification system when:
- **Updating draft definitions** via `updateDraft()`
- **Publishing workflows** via `publish()` - blocks publication if validation fails

### 2. **WorkflowDefinitionValidator**
The public API wrapper that converts workflow definitions into validation results. Returns a structured array with:
- `is_publishable` - Whether the workflow can be published
- `summary` - Count of errors and warnings
- `issues` - Detailed list of all validation issues
- `errors`/`warnings` - Simple arrays of error/warning messages

### 3. **WorkflowVerificationService**
The core verification engine that:
- Normalizes the workflow definition for consistent processing
- Builds a graph representation of the workflow structure
- Executes all verification rules in sequence
- Aggregates results into a comprehensive report

## Workflow Verification Flow

```
User Definition
       ↓
WorkflowManagementService.updateDraft()
       ↓
WorkflowDefinitionValidator.validate()
       ↓
WorkflowVerificationService.verify()
       ├→ WorkflowDefinitionNormalizer.normalize()
       ├→ WorkflowDefinitionGraph.build()
       └→ Execute Verification Rules (in order):
           1. SyntaxVerificationRule
           2. GraphControlFlowVerificationRule
           3. ExpressionVerificationRule
           4. ContextualVerificationRule
       ↓
WorkflowVerificationResult
       ↓
Returned to Client
```

## Verification Stages Explained

### Stage 1: Normalization
- Converts raw input into a standardized format
- Handles legacy or alternate property names (e.g., `source` vs `source_node_key`)
- Fills in defaults where applicable
- Preserves the original definition for reference

### Stage 2: Graph Construction
- Builds an in-memory representation of the workflow graph
- Indexes nodes and edges for efficient lookups
- Calculates node relationships (incoming/outgoing connections)
- Used by later rules for reachability and flow analysis

### Stage 3: Verification Rules (Sequential)
Each rule builds upon the previous stage:

| Rule | Purpose | Severity |
|------|---------|----------|
| **Syntax** | Validates structure, required fields, types | Errors block publication |
| **Graph Control Flow** | Ensures proper connectivity and reachability | Errors block publication |
| **Expression** | Validates conditional expressions and boolean logic | Errors block publication |
| **Contextual** | Validates references to actual users, documents, etc. | Errors block publication |

## Key Features

### ✅ Multi-Stage Validation
Early stages (syntax) catch basic issues before more expensive checks run.

### ✅ Detailed Error Messages
Each violation includes:
- Severity (error or warning)
- Error code (machine-readable identifier)
- Human-readable message
- Path to the problematic element
- Node ID and/or Edge ID for graph elements

### ✅ Workflow Graph Analysis
- **Reachability**: All nodes must be reachable from the entry node
- **Terminal nodes**: At least one terminal node must exist
- **Cycle detection**: Detects invalid cycles (except in explicit loop nodes)
- **Degree analysis**: Validates incoming/outgoing connections per node

### ✅ Expression Validation
- Parses conditional expressions (edge conditions, node expressions)
- Validates boolean semantics
- Identifies undefined variable references
- Supports operators: `&&`, `||`, `==`, `!=`, `>`, `<`, `>=`, `<=`, `!`

### ✅ Contextual Checks
- Validates that referenced users exist and are team members
- Ensures knowledge base documents belong to the same tenant
- Validates human task assignments

## Example Usage

```php
// In WorkflowManagementService
$validation = $this->definitionValidator->validate($data['definition'], $workflow, $actor);

if ((bool) ($data['validate_only'] ?? false)) {
    return [
        'saved' => false,
        'validation' => $validation,
    ];
}

// Or during publish (must be publishable)
if (! $validation['is_publishable']) {
    throw ValidationException::withMessages([
        'definition' => $validation['errors'],
    ]);
}
```

## Return Structure

```php
[
    'is_publishable' => true/false,  // Can this workflow be published?
    'summary' => [
        'errors' => 0,       // Number of blocking issues
        'warnings' => 2,     // Number of non-blocking issues
    ],
    'issues' => [
        [
            'severity' => 'error'|'warning',
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
        // ... simple error messages
    ],
    'warnings' => [
        // ... simple warning messages
    ],
]
```

## When Verification Runs

1. **On Draft Update**: Non-blocking check to give real-time feedback to users
2. **Before Publish**: Blocking check - publication fails if `is_publishable` is false
3. **Manual Validation**: Users can request validation-only without saving

## Design Principles

- **Early termination**: Syntax errors are caught first to avoid cascading failures
- **Rule isolation**: Each rule is independent and can be tested/updated separately
- **Complete reporting**: Reports all issues, not just the first one
- **Context preservation**: Errors include enough information to pinpoint the problem
- **Extensibility**: New rules can be added by implementing `VerificationRule` interface
