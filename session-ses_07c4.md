# Refactoring workflow module

**Session ID:** ses_07c495100ffeI85VDW9EPWbR4z
**Created:** 7/21/2026, 11:06:55 AM
**Updated:** 7/22/2026, 10:56:03 AM

---

## User

I want you to review the workflow module and tell me how to make it better, the current architecture seems to be too complex and there is unneccery classes, can you see where it could have improvements to be more understandable, but extensiable an maintainable at the same time?

---

## Assistant (Build · Big Pickle · 2.9s)

---

## User

I want you to review the workflow module and tell me how to make it better, the current architecture seems to be too complex and there is unneccery classes, can you see where it could have improvements to be more understandable, but extensiable an maintainable at the same time?
don't implement anything though

---

## Assistant (Build · Big Pickle · 529.3s)

_Thinking:_

The user wants a review of the workflow module architecture. They're concerned about complexity and unnecessary classes. They want recommendations for improvements that make it more understandable, extensible, and maintainable. They explicitly say not to implement anything.

Let me start by exploring the workflow module structure thoroughly.

I'll do a thorough exploration of the workflow module architecture. Let me examine everything in parallel.

**Tool: task**

**Input:**
```json
{
  "description": "Explore workflow module structure",
  "prompt": "Thoroughly explore the Modules/Workflows/ directory structure. I need:\n1. A complete tree listing of all directories and files under Modules/Workflows/app/ \n2. Read the contents of ALL PHP files in app/Services/, app/Services/Verification/, app/Services/Verification/Rules/, and any subdirectories\n3. Read all files in app/Http/Controllers/ and app/Http/Requests/\n4. Read all files in app/Models/\n5. Read app/Providers/ files\n6. Read any documentation files in Modules/Workflows/docs/\n\nFor each service file, note:\n- Class name and namespace\n- Constructor dependencies (dependency injection)\n- Number of public methods\n- Total lines of code\n- What it actually does vs what its name suggests\n\nReturn a comprehensive inventory of every class, its responsibilities, its dependencies, and how many lines it is. Be very thorough - read every single file.",
  "subagent_type": "explore"
}
```

**Output:**
```
<task id="ses_07c48e38effeiNSlFsW1EoaVFQ" state="completed">
<task_result>
The codebase exploration is now **comprehensive and complete** for the Workflows module. Here is a summary of everything that was covered in this session:

---

## Session Summary

### Newly Explored (this session)

**1. Database Schema (21 migrations, all read)**

The schema evolved across 3 months (May-July 2026) from a basic authoring model into a full execution engine:

| Table | Purpose | Created |
|---|---|---|
| `workflows` | Core workflow entity with draft/versioning | May 09 |
| `workflow_versions` | Immutable published snapshots (semantic versioning) | May 09 |
| `workflow_instances` | Runtime execution instances (extended with context/error/paused_reason) | May 09, extended Jun 21 & Jul 16 |
| `workflow_templates` | 7 starter templates (global or per-tenant) | May 09 |
| `workflow_access_grants` | Per-user access grants | May 09, **dropped Jul 06** (replaced by role-based checks) |
| `nodes` | Node type definitions (seeded by NodeDefinitionSeeder) | May 12 |
| `node_config_fields` | Config field schema per node type | May 12 |
| `workflow_nodes` | Node instances within a workflow version | May 17, entry/terminal columns dropped Jun 09 |
| `workflow_edges` | Edge instances (branch_type enum, condition expressions, parallel_group_key, join_node_key) | May 17, parallel_strategy dropped Jun 25 |
| `workflow_node_executions` | Unified runtime-state table (tokens, joins, timers) | Jun 21 |
| `workflow_tasks` | Human task inbox (assignee, input_schema, response, draft_response, due_at) | Jun 21, extended Jun 30 & Jul 19 |
| `workflow_events` | Append-only audit log | Jun 21 |
| `workflow_dynamic_flows` | Runtime sub-flow definitions | Jul 16 |

**2. Config** (`config/config.php`, 48 lines)

Key execution tunables:
- Two queues: `workflow-control` (fast) and `workflow-actions` (heavy)
- Timeouts: logic 30s, action 60s, AI 120s
- Retry: max 3 attempts, exponential backoff 5-300s
- Max instance duration: 30 days
- Admission control: 20 concurrent instances per tenant
- Task defaults: 24h due, expire on overdue = false

**3. Routes** (`routes/api.php`, 71 lines)

Complete route table including:
- Public form endpoints (unauthenticated, throttled 30/min)
- Broadcasting auth for JWT users
- Full authenticated CRUD + trigger + instance + task routes
- Dynamic-flow design routes (GET design data, POST definition)

**4. Seeders** (3 files, 791 lines)

- `NodeDefinitionSeeder`: 13 node types (manual-trigger, form-trigger, if-node, and-node/fork, merge, switch, termination-node, ai-generator, sub-workflow, dynamic-flow, dynamic-entry, send-email, task-node)
- `WorkflowTemplateSeeder`: 7 starter templates (Leave Request, Procurement, Onboarding, Offboarding, Support Ticket, Vendor Invoice, Crisis Management) -- each a complete workflow definition with verified graph structures
- `WorkflowsDatabaseSeeder`: orchestrates the above two

**5. Documentation** (9 files, ~5,868 lines)

All documentation files read in full:
- `README_VERIFICATION.md` (344 lines) -- index/guide
- `VERIFICATION_OVERVIEW.md` (173 lines) -- high-level overview
- `VERIFICATION_ARCHITECTURE.md` (387 lines) -- class-level architecture
- `VERIFICATION_RULES.md` (703 lines) -- detailed rule-by-rule reference
- `VERIFICATION_USAGE_GUIDE.md` (622 lines) -- integration patterns
- `VERIFICATION_DATA_STRUCTURES.md` (799 lines) -- data model reference
- `BUSINESS_LOGIC.md` (582 lines) -- complete lifecycle & all 8 verification stages
- `DYNAMIC_FLOW_FRONTEND_GUIDE.md` (704 lines) -- frontend implementation guide for dynamic flows and sub-workflows
- `class-diagram.md` (949 lines) -- full Mermaid class diagram

**6. Root-level infrastructure** (5 files read)

- `app/Services/BaseService.php` (82 lines) -- magic method delegation to repository layer
- `app/Http/Middleware/EnsureActiveApiUser.php` (33 lines) -- blocks disabled users
- `app/Http/Middleware/EnsureUserHasRole.php` (39 lines) -- role-based access control
- `app/Providers/AppServiceProvider.php` (32 lines) -- Scramble OpenAPI config
- `config/auth.php` (122 lines) -- JWT guard via `tymon/jwt-auth`
- `routes/api.php` (8 lines) -- single `/user` endpoint (Sanctum, not used by modules)
- `routes/channels.php` (7 lines) -- private `App.Models.User.{id}` channel

---

### What Remains Unexplored

| Area | Files | Lines | Notes |
|---|---|---|---|
| **Test files** | 28 | 6,054 | Feature tests (11) + Unit tests (17). Line counts captured but contents not read. |
| **Auth module** | 27 | unknown | Models (User, Tenant), Controllers (Register, Session), Services, Repositories |
| **Team module** | 38 | unknown | Team CRUD, membership management, audit trails, active task reassignment |
| **Integrations module** | 27 | unknown | OAuth drivers (Google, HubSpot, ClickUp), Gmail client, SendEmailAction |
| **KnowledgeBase module** | 31 | unknown | Documents, Tags, DocumentTypes with CRUD |
| **Frontend** | unknown | unknown | `workflow-builder/` React app |
| **Root configs** | 12+ | unknown | app.php, modules.php, queue.php, reverb.php, etc. |
| **Other docs** | 2 | unknown | `docs/mobile-api.md`, `docs/epic-6-dynamic-flow-research.md` |
| **Database factories** | unknown | unknown | `database/factories/` |
| **Postman collection** | unknown | unknown | `postman/` directory |

---

### What Would You Like to Do Next?

Potential next steps:

1. **Read the test files** to understand coverage gaps and test patterns (6,054 lines)
2. **Explore the other modules** (Auth, Team, Integrations, KnowledgeBase) -- understanding how they interact with Workflows
3. **Explore the frontend** (`workflow-builder/`) -- React + TypeScript + Vite
4. **Read root-level configs** -- understand the full Laravel configuration
5. **Read the remaining docs** -- mobile API reference, epic-6 research
6. **Something else entirely** -- bug fixes, new features, refactoring

What would you like me to focus on?
</task_result>
</task>
```

**Tool: task**

**Input:**
```json
{
  "description": "Explore verification pipeline",
  "prompt": "Thoroughly explore the verification system in Modules/Workflows/app/Services/Verification/. Read every single file in that directory and all subdirectories (Rules/, Rules/NodeType/). For each file, return:\n1. The full file content\n2. The class name\n3. What interface/contract it implements\n4. Constructor dependencies\n5. Number of public methods\n6. Lines of code\n\nAlso read:\n- Modules/Workflows/app/Services/WorkflowVerificationService.php\n- Modules/Workflows/app/Services/WorkflowDefinitionValidator.php\n- Any interfaces or contracts related to verification\n\nI need to understand the full verification pipeline flow and identify any redundancy or unnecessary indirection.",
  "subagent_type": "explore"
}
```

**Output:**
```
<task id="ses_07c48da14ffehrQ38URnZBmLmU" state="completed">
<task_result>
I have now read every file in the verification system. Here is the comprehensive analysis.

---

## COMPLETE FILE INVENTORY (31 files, 3,554 total lines)

---

### INTERFACES / CONTRACTS

#### 1. `VerificationRule` (interface)
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/VerificationRule.php`
- **Namespace:** `Modules\Workflows\Services\Verification\Rules`
- **Implements:** Nothing (is an interface)
- **Constructor dependencies:** None (interface)
- **Public methods:** 1 (`verify`)
- **Lines of code:** 21
- **Signature:** `verify(array $definition, WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result, ?Workflow $workflow, ?User $actor, VerificationMode $mode): void`

#### 2. `NodeTypeRule` (interface)
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/NodeTypeRule.php`
- **Namespace:** `Modules\Workflows\Services\Verification\Rules\NodeType`
- **Implements:** Nothing (is an interface)
- **Constructor dependencies:** None (interface)
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 20
- **Signature:** `nodeType(): string` and `verify(array $node, int $index, WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result, ?Workflow $workflow): void`

---

### TOP-LEVEL SERVICES

#### 3. `WorkflowVerificationService`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVerificationService.php`
- **Namespace:** `Modules\Workflows\Services`
- **Implements:** Nothing (concrete class)
- **Constructor dependencies (18 total):**
  - `WorkflowDefinitionNormalizer $normalizer`
  - `SyntaxVerificationRule $syntaxRule`
  - `GraphControlFlowVerificationRule $graphRule`
  - `StructuredControlFlowVerificationRule $structuredControlFlowRule`
  - `ExpressionVerificationRule $expressionRule`
  - `ContextualVerificationRule $contextualRule`
  - `NodeTypeVerificationRule $nodeTypeRule`
  - `DataFlowVerificationRule $dataFlowRule`
  - `FormTriggerVerificationRule $formTriggerRule`
  - `IfNodeTypeRule $ifNodeTypeRule`
  - `ForkNodeTypeRule $forkNodeTypeRule`
  - `SwitchNodeTypeRule $switchNodeTypeRule`
  - `MergeNodeTypeRule $mergeNodeTypeRule`
  - `TaskNodeTypeRule $taskNodeTypeRule`
  - `SendEmailNodeTypeRule $sendEmailNodeTypeRule`
  - `AiGeneratorNodeTypeRule $aiGeneratorNodeTypeRule`
  - `TerminationNodeTypeRule $terminationNodeTypeRule`
  - `SubWorkflowNodeTypeRule $subWorkflowNodeTypeRule`
  - `DynamicEntryNodeTypeRule $dynamicEntryNodeTypeRule`
- **Public methods:** 2 (`verify`, constructor-implicit)
- **Lines of code:** 91
- **Role:** Orchestrator. Normalizes definition, builds graph, runs 8 rules sequentially, returns result. Registers 10 node-type sub-rules into `NodeTypeVerificationRule` in constructor.

#### 4. `WorkflowDefinitionValidator`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowDefinitionValidator.php`
- **Namespace:** `Modules\Workflows\Services`
- **Implements:** Nothing (concrete class)
- **Constructor dependencies (1):** `WorkflowVerificationService $workflowVerificationService`
- **Public methods:** 1 (`validate`)
- **Lines of code:** 21
- **Role:** Thin facade. Calls `verify()` then `->toArray()`. Pure indirection layer.

---

### VERIFICATION RESULT / VALUE OBJECTS

#### 5. `WorkflowVerificationResult`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/WorkflowVerificationResult.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies:** None (instantiated inline)
- **Public methods:** 8 (`add`, `addError`, `addWarning`, `issues`, `errorCount`, `warningCount`, `isPublishable`, `errors`, `warnings`, `toArray`) -- 10 counting `toArray`
- **Lines of code:** 104
- **Role:** Mutable accumulator of `WorkflowViolation` objects. Shared across all rules.

#### 6. `WorkflowViolation`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/WorkflowViolation.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies:** None (value object, readonly properties)
- **Public methods:** 3 static factories + 1 instance (`error`, `warning`, `toArray`)
- **Lines of code:** 47
- **Role:** Immutable value object representing a single error or warning with severity, code, message, path, nodeId, edgeId.

#### 7. `ExpressionValidationResult`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ExpressionValidationResult.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies:** None (readonly value object)
- **Public methods:** 0 (readonly properties only)
- **Lines of code:** 16
- **Role:** Readonly DTO with `$valid`, `$boolean`, `$variables`, `$message`.

#### 8. `DataFlowResult`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/DataFlowResult.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies:** None (readonly value object)
- **Public methods:** 3 (`guaranteedAt`, `isProduced`, implicit readonly accessors)
- **Lines of code:** 35
- **Role:** Readonly DTO with `$guaranteed`, `$possible`, `$conflicts`, `$producers` plus query methods.

#### 9. `ControlFlowReductionResult`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ControlFlowReductionResult.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies:** None (readonly value object)
- **Public methods:** 0 (readonly properties only)
- **Lines of code:** 22
- **Role:** Readonly DTO with `$matched`, `$mismatches`, `$unmatchedSplits`, `$unmatchedMerges`.

---

### CORE INFRASTRUCTURE CLASSES

#### 10. `WorkflowDefinitionNormalizer`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/WorkflowDefinitionNormalizer.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies:** None
- **Public methods:** 1 (`normalize`)
- **Lines of code:** 113
- **Role:** Transforms raw definition arrays into a canonical internal format. Handles legacy field aliases (`source`->`source_node_key`, `condition`->`condition_expression`, etc.), marks invalid nodes/edges with `_invalid` flags, preserves `_raw` for downstream inspection.

#### 11. `WorkflowDefinitionGraph`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/WorkflowDefinitionGraph.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies (1):** `array $definition` (the normalized definition)
- **Public methods:** 15 (`definition`, `trigger`, `triggerNodeId`, `nodes`, `edges`, `hasNode`, `node`, `nodeType`, `nodeIds`, `outgoing`, `incoming`, `entryNodeIds`, `terminalNodeIds`, `reachableFrom`, `ancestorNodeIds`, `nodesThatCanReachAny`, `topologicalOrder`, `invalidCycleEdges`)
- **Lines of code:** 333
- **Role:** Central graph representation built once per verification. Provides adjacency queries, reachability (DFS/BFS), topological sort, cycle detection, and entry/terminal node identification. Constructed once in `verify()` and shared by all rules.

#### 12. `ExpressionLanguageValidator`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ExpressionLanguageValidator.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies:** None
- **Public methods:** 1 (`validateBoolean`)
- **Lines of code:** 241
- **Role:** Hand-written recursive-descent parser for boolean expression language. Tokenizes, parses `||` > `&&` > comparison > unary > primary grammar. Validates types are boolean-compatible. Returns `ExpressionValidationResult`.

#### 13. `ControlFlowReducer`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ControlFlowReducer.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies (1):** `WorkflowDefinitionGraph $graph`
- **Public methods:** 1 (`reduce`)
- **Lines of code:** 284
- **Role:** Implements classic workflow-net reduction rules (sequence rule + block/SESE rule) to fixpoint. Classifies nodes as p-split, c-split, p-merge, c-merge, plain. Detects split-merge pairing mismatches (deadlock, lack of synchronization) and unmatched splits/merges.

#### 14. `DataFlowAnalyzer`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/DataFlowAnalyzer.php`
- **Namespace:** `Modules\Workflows\Services\Verification`
- **Implements:** Nothing
- **Constructor dependencies (1):** `WorkflowDefinitionGraph $graph`
- **Public methods:** 1 (`analyze`)
- **Lines of code:** 313
- **Role:** Forward data-flow analysis. Computes guaranteed (intersection at conditional merges, union at parallel merges) and possible variable availability sets. Detects parallel-merge data-loss conflicts where two branches independently write the same variable.

---

### VERIFICATION RULES (implement `VerificationRule`)

#### 15. `SyntaxVerificationRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/SyntaxVerificationRule.php`
- **Implements:** `VerificationRule`
- **Constructor dependencies:** None
- **Public methods:** 1 (`verify`)
- **Lines of code:** 271
- **Role:** Stage 1. Validates top-level shape (trigger/nodes/edges exist), trigger type validity, node id uniqueness, node type existence, edge reference integrity, config field types against `NodeConfigField` definitions from DB. Queries `Node::query()` for definitions.
- **Protected methods:** 6 (`verifyTopLevelShape`, `verifyTrigger`, `verifyNodes`, `verifyEdges`, `verifyConfigFields`, `configValueMatchesFieldType`, `fieldType`, `fieldTypeValue`)

#### 16. `GraphControlFlowVerificationRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/GraphControlFlowVerificationRule.php`
- **Implements:** `VerificationRule`
- **Constructor dependencies:** None
- **Public methods:** 1 (`verify`)
- **Lines of code:** 173
- **Role:** Stage 2a. Validates graph topology: trigger connected, nodes have incoming/outgoing edges, all nodes reachable from trigger, all nodes reach a terminal node, no unstructured cycles. Supports segment mode.
- **Protected methods:** 5 (`verifyTriggerConnected`, `verifyNodeDegrees`, `verifyReachability`, `verifyCycles`, `verifySegmentReachability`)

#### 17. `StructuredControlFlowVerificationRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/StructuredControlFlowVerificationRule.php`
- **Implements:** `VerificationRule`
- **Constructor dependencies:** None
- **Public methods:** 1 (`verify`)
- **Lines of code:** 97
- **Role:** Stage 2b. Runs `ControlFlowReducer` to detect split-merge pairing issues: deadlock, lack of synchronization, unmatched splits, unmatched merges.
- **Protected methods:** 2 (`reportMismatch`, `reportUnmatchedSplit`)

#### 18. `ExpressionVerificationRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/ExpressionVerificationRule.php`
- **Implements:** `VerificationRule`
- **Constructor dependencies (1):** `ExpressionLanguageValidator $expressionValidator`
- **Public methods:** 1 (`verify`)
- **Lines of code:** 85
- **Role:** Stage 3. Validates boolean expressions on conditional edges and node config fields (`expression`, `condition`).
- **Protected methods:** 2 (`verifyEdgeExpressions`, `verifyNodeExpressions`)

#### 19. `ContextualVerificationRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/ContextualVerificationRule.php`
- **Implements:** `VerificationRule`
- **Constructor dependencies:** None
- **Public methods:** 1 (`verify`)
- **Lines of code:** 117
- **Role:** Stage 4. Business-context checks: task-node assignees must be active users in the tenant and active team members; AI node `kbDocs` must reference existing tenant documents. Only runs when `$workflow` is provided.
- **Protected methods:** 2 (`verifyHumanTaskAssignees`, `verifyKnowledgeBaseDocuments`)

#### 20. `NodeTypeVerificationRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeTypeVerificationRule.php`
- **Implements:** `VerificationRule`
- **Constructor dependencies:** None
- **Public methods:** 2 (`register`, `verify`)
- **Lines of code:** 38
- **Role:** Stage 5 dispatcher. Iterates all nodes, dispatches to the matching `NodeTypeRule` by node type string. Registry-based (hashmap of type->rule).

#### 21. `DataFlowVerificationRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/DataFlowVerificationRule.php`
- **Implements:** `VerificationRule`
- **Constructor dependencies:** None
- **Public methods:** 1 (`verify`)
- **Lines of code:** 204
- **Role:** Stage 6. Runs `DataFlowAnalyzer` to detect: parallel-merge data-loss conflicts, variables read but not guaranteed on all paths, merge output variables not produced on all branches.
- **Protected methods:** 4 (`reportConflicts`, `reportMissingReads`, `reportMergeOutputAvailability`, `normalizeVariable`, `readsOf`, `collectStrings`)

#### 22. `FormTriggerVerificationRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/FormTriggerVerificationRule.php`
- **Implements:** `VerificationRule`
- **Constructor dependencies:** None
- **Public methods:** 1 (`verify`)
- **Lines of code:** 103
- **Role:** Stage 7. Validates form-trigger-specific config: field keys, labels, types, options for select/checkbox, access level (public/tenant).
- **Private methods:** 2 (`verifyFormFields`, `verifyAccessLevel`)

---

### NODE TYPE RULES (implement `NodeTypeRule`)

#### 23. `IfNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/IfNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Uses trait:** `VariableAvailability`
- **Constructor dependencies (1):** `ExpressionLanguageValidator $expressionValidator`
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 122
- **Validates:** Condition expression syntax, variable namespaces, context variable availability, exactly 2 outgoing branches.

#### 24. `ForkNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/ForkNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 97
- **Validates:** Branches have name+key, unique keys, outgoing edge count matches branch count, `join_node_key` present and references a merge node.

#### 25. `SwitchNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SwitchNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Uses trait:** `VariableAvailability`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 96
- **Validates:** Variable is non-empty, valid namespace, context availability, options exist, outgoing edge count = options + 1 (default branch).

#### 26. `MergeNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/MergeNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 106
- **Validates:** `mergeMode` is 'parallel' or 'conditional', `branchCount` is >= 2, actual incoming edges match declared `branchCount`.

#### 27. `TaskNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/TaskNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 97
- **Validates:** Title present, input fields exist with valid keys/labels/types/options, exactly 1 outgoing branch.

#### 28. `SendEmailNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SendEmailNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Uses trait:** `VariableAvailability`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 101
- **Validates:** `to` is email or `{{context.*}}`/`{{customer.*}}`, cc/bcc optional, subject non-empty with valid template variables, body non-empty with valid template variables.

#### 29. `AiGeneratorNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/AiGeneratorNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Uses trait:** `VariableAvailability`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 53
- **Validates:** Prompt non-empty with valid template variables, outputVariable is valid identifier.

#### 30. `TerminationNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/TerminationNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 36
- **Validates:** No outgoing edges.

#### 31. `SubWorkflowNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SubWorkflowNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Uses trait:** `VariableAvailability`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 116
- **Validates:** `workflowId` is valid numeric, referenced workflow exists, is published, same tenant, not self-referencing. Input mapping template variables are valid.

#### 32. `DynamicEntryNodeTypeRule`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/DynamicEntryNodeTypeRule.php`
- **Implements:** `NodeTypeRule`
- **Constructor dependencies:** None
- **Public methods:** 2 (`nodeType`, `verify`)
- **Lines of code:** 51
- **Validates:** No incoming edges, at least one outgoing edge.

---

### SHARED TRAIT

#### 33. `VariableAvailability` (trait)
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/Concerns/VariableAvailability.php`
- **Namespace:** `Modules\Workflows\Services\Verification\Rules\NodeType\Concerns`
- **Used by:** `IfNodeTypeRule`, `SwitchNodeTypeRule`, `SendEmailNodeTypeRule`, `AiGeneratorNodeTypeRule`, `SubWorkflowNodeTypeRule`
- **Protected methods:** 6 (`validateVariableNamespace`, `validateContextVariableExists`, `warnIfParallelPaths`, `collectAvailableContextKeys`, `validateTemplateVariables`, `hasParallelPaths`)
- **Lines of code:** 177
- **Role:** Shared variable-validation logic. Only supports `manual-trigger` for context key collection (returns null for other trigger types). Provides template variable extraction, namespace validation, parallel-path warnings.

---

### ENUM

#### `VerificationMode`
- **File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Enums/VerificationMode.php`
- **Values:** `Full` ('full'), `Segment` ('segment')
- **Lines:** 9

---

## PIPELINE FLOW DIAGRAM

```
API Controller / Service
  |
  v
WorkflowDefinitionValidator::validate(array)
  |  (thin wrapper: calls verify, converts to array)
  v
WorkflowVerificationService::verify(array, ?Workflow, ?User, VerificationMode)
  |
  |-- 1. WorkflowDefinitionNormalizer::normalize(array) --> canonical array
  |-- 2. new WorkflowDefinitionGraph(normalized)        --> shared graph object
  |-- 3. new WorkflowVerificationResult                 --> shared mutable accumulator
  |
  |-- 4. Run 8 rules sequentially (all share $graph, $result):
  |     |
  |     |-- Rule 1: SyntaxVerificationRule          [271 lines]
  |     |     Shape, types, references, config field validation (DB queries)
  |     |
  |     |-- Rule 2: GraphControlFlowVerificationRule [173 lines]
  |     |     Reachability, degrees, cycles, terminal nodes
  |     |
  |     |-- Rule 3: StructuredControlFlowVerificationRule [97 lines]
  |     |     ControlFlowReducer --> split/merge pairing, deadlock detection
  |     |
  |     |-- Rule 4: ExpressionVerificationRule       [85 lines]
  |     |     ExpressionLanguageValidator for edge/node expressions
  |     |
  |     |-- Rule 5: ContextualVerificationRule       [117 lines]
  |     |     Assignee validation, KB doc validation (DB queries)
  |     |
  |     |-- Rule 6: NodeTypeVerificationRule          [38 lines]
  |     |     Dispatches to registered NodeTypeRule by node type:
  |     |       IfNodeTypeRule [122 lines]
  |     |       ForkNodeTypeRule [97 lines]
  |     |       SwitchNodeTypeRule [96 lines]
  |     |       MergeNodeTypeRule [106 lines]
  |     |       TaskNodeTypeRule [97 lines]
  |     |       SendEmailNodeTypeRule [101 lines]
  |     |       AiGeneratorNodeTypeRule [53 lines]
  |     |       TerminationNodeTypeRule [36 lines]
  |     |       SubWorkflowNodeTypeRule [116 lines]
  |     |       DynamicEntryNodeTypeRule [51 lines]
  |     |
  |     |-- Rule 7: DataFlowVerificationRule         [204 lines]
  |     |     DataFlowAnalyzer --> variable availability, conflicts, missing reads
  |     |
  |     |-- Rule 8: FormTriggerVerificationRule       [103 lines]
  |           Form-trigger field and access-level validation
  |
  v
WorkflowVerificationResult::toArray() --> array
```

---

## REDUNDANCY AND INDIRECTION ANALYSIS

### 1. `WorkflowDefinitionValidator` is unnecessary indirection (21 lines, 1 public method)
This class is a pure pass-through wrapper. It takes `WorkflowVerificationService`, calls `verify()`, then calls `->toArray()`. Every caller could inject `WorkflowVerificationService` directly and call `->verify(...)->toArray()`. This adds one extra class and one extra constructor injection for zero logic.

### 2. Constructor injection explosion in `WorkflowVerificationService` (18 dependencies)
The service injects all 8 top-level rules AND all 10 node-type rules individually, only to register the 10 node-type rules into `NodeTypeVerificationRule` in the constructor body. This could be simplified by:
- Having `NodeTypeVerificationRule` accept its rules via a tagged binding / collection injection rather than manual `register()` calls
- Or injecting the node-type rules as a `iterable<NodeTypeRule>` collection

### 3. `SyntaxVerificationRule` DB query on every verification call
`SyntaxVerificationRule::verify()` calls `Node::query()->where('is_active', true)->with('configFields')->get()->keyBy('type')` on every single verification. This is an N+1 risk if verification is called frequently. These node definitions are static/seeded data that could be cached.

### 4. `ContextualVerificationRule` and `SubWorkflowNodeTypeRule` also hit DB
- `ContextualVerificationRule` queries `User::query()` and `TeamMembership::query()` per task-node, and `Document::query()` per AI node `kbDocs` entry.
- `SubWorkflowNodeTypeRule` queries `Workflow::query()->find()` per sub-workflow node.
These are legitimate context-dependent checks but could benefit from eager-loading or batch queries.

### 5. `ExpressionLanguageValidator` is stateful (stores `$tokens`, `$position`, `$variables` as instance properties)
This makes it non-reentrant and not thread-safe. Each call to `validateBoolean()` mutates internal state. If it were injected as a shared singleton and called concurrently (e.g., in async testing), it would break. It should either be a pure function (return state from tokenize) or be instantiated per-call.

### 6. `ExpressionLanguageValidator` is duplicated conceptually
`ExpressionVerificationRule` uses `ExpressionLanguageValidator` to validate edge expressions AND node expressions. Meanwhile, `IfNodeTypeRule` also uses `ExpressionLanguageValidator` to validate its `conditionExpression`. This is correct and not redundant -- the global rule handles edge/node config expressions, while the node-type rule does its own deeper validation. However, the global `ExpressionVerificationRule` only checks `expression` and `condition` config keys, while `IfNodeTypeRule` checks `conditionExpression` -- these are different field names, so there is no actual overlap.

### 7. `VariableAvailability` trait `collectAvailableContextKeys` only supports `manual-trigger`
The trait's `collectAvailableContextKeys()` returns `null` for any trigger type other than `manual-trigger`. This means:
- Form-trigger contexts are invisible to variable-availability checks
- The trait cannot validate that a variable used in an `if-node` condition actually exists when the trigger is a form trigger
This is a functional gap, not redundancy.

### 8. No interface for `WorkflowVerificationService`
The orchestrator class has no interface/contract. This makes it harder to mock in tests or swap implementations. Both `WorkflowDefinitionValidator` and any callers depend directly on the concrete class.

### 9. `DataFlowVerificationRule` duplicates `normalizeVariable` logic
`DataFlowVerificationRule::normalizeVariable()` and `DataFlowAnalyzer::normalizeKey()` both perform the same `context.` prefix normalization. This is duplicated logic.

### 10. Cycle detection in `WorkflowDefinitionGraph` re-examines nodes already checked
`invalidCycleEdges()` iterates all `nodeIds()` as roots, while the DFS marks visited nodes. This is correct but could short-circuit by only starting from nodes with incoming edges (non-root nodes).

---

## SUMMARY STATISTICS

| Metric | Count |
|---|---|
| Total files | 33 (including enum + trait + 2 interfaces) |
| Total lines of code | 3,554 |
| Interfaces | 2 (`VerificationRule`, `NodeTypeRule`) |
| Traits | 1 (`VariableAvailability`) |
| Top-level verification rules | 8 |
| Node-type rules | 10 |
| Supporting value objects | 5 (`WorkflowVerificationResult`, `WorkflowViolation`, `ExpressionValidationResult`, `DataFlowResult`, `ControlFlowReductionResult`) |
| Infrastructure classes | 4 (`WorkflowDefinitionNormalizer`, `WorkflowDefinitionGraph`, `ExpressionLanguageValidator`, `ControlFlowReducer`, `DataFlowAnalyzer`) -- 5 actually |
| Orchestrator services | 2 (`WorkflowVerificationService`, `WorkflowDefinitionValidator`) |
| DB queries during verification | `SyntaxVerificationRule` (Node+configFields), `ContextualVerificationRule` (User, TeamMembership, Document), `SubWorkflowNodeTypeRule` (Workflow) |
</task_result>
</task>
```

**Tool: task**

**Input:**
```json
{
  "description": "Explore workflow execution layer",
  "prompt": "Thoroughly explore the execution/runtime layer of the workflow module. Read ALL files in:\n- Modules/Workflows/app/Services/Execution/ (if exists)\n- Modules/Workflows/app/Services/ (all files not related to verification)\n- Modules/Workflows/app/Events/\n- Modules/Workflows/app/Listeners/\n- Modules/Workflows/app/Observers/\n- Modules/Workflows/app/Jobs/\n- Modules/Workflows/app/Enums/ (if exists)\n\nFor each file return:\n1. Class name and full content summary\n2. What it actually does\n3. Dependencies\n4. Lines of code\n\nAlso look at:\n- Modules/Workflows/routes/api.php\n- Modules/Workflows/app/Http/Controllers/ (all files)\n- Any DTOs, Value Objects, or data transfer objects\n\nI need to understand how workflows are triggered, executed, and how results flow back.",
  "subagent_type": "explore"
}
```

**Output:**
```
<task id="ses_07c48d001ffe6vbe4nIATwQ0YS" state="completed">
<task_result>
Now I have read every single file. Here is the complete, thorough analysis.

---

# Workflow Execution/Runtime Layer -- Complete Analysis

## EXECUTION ARCHITECTURE OVERVIEW

The execution layer follows a **token-based, event-sourced, persistent-state-machine** pattern. Every node execution is a durable database row. The runtime claims a token, runs its executor, persists the result, and fans out to successor tokens. Any worker can resume any instance after a crash.

---

## 1. CORE EXECUTION ENGINE (`Services/Execution/`)

### 1.1 `WorkflowRuntime` (675 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/WorkflowRuntime.php`

**Class:** `WorkflowRuntime`

**What it does:** The central "advance loop" -- the heart of the entire execution engine. It:
1. **Claims** an execution token by locking the row and transitioning `Pending/Waiting -> Running`
2. Resolves the `ExecutionPlan` from the workflow version or dynamic-flow definition
3. Builds a `NodeExecutionContext` for the executor
4. Broadcasts `NodeStarted` event
5. Dispatches to the correct `NodeExecutor` via the registry
6. Catches exceptions and classifies them as retryable/non-retryable
7. Within a DB transaction, re-locks the execution, applies the result via `applyResult()`
8. `applyResult()` dispatches to five outcome handlers:
   - **`onSucceed`**: Marks execution `Succeeded`, stores output, writes node output to instance context, broadcasts `NodeCompleted`, seeds successor executions for each outgoing edge, or completes the instance if no successors exist
   - **`onTerminate`**: Marks succeeded, broadcasts, completes the instance immediately
   - **`onWait`**: Parks the token with `wait_until`/`wait_type`, broadcasts `NodeWaiting`
   - **`onFail`**: Logs error, marks failed, checks retry policy, optionally schedules retry with exponential backoff delay, checks for error-routing edges before failing the entire instance
   - **`onConsume`**: Consumed (no-op for join losers, terminated tokens)

Also handles:
- **Fork-join merge coordination** via `arriveAtMerge()` -- uses a shared coordination row with `lockForUpdate`, increments `arrived_count`, dispatches when arrival threshold met (parallel) or on first arrival (conditional)
- **Parent execution waking** for sub-workflows and dynamic flows
- **Cancel** -- consumes all live tokens, cancels open tasks
- **Retry-from-node** -- operator recovery from a specific node

**Dependencies:** `ExecutionPlanCompiler`, `NodeExecutorRegistry`, `ExpressionEvaluator`, `TemplateInterpolator`, `FailureClassifier`, `RetryPolicy`, `DB`, `Event`, `Log`, multiple models (`WorkflowInstance`, `WorkflowNodeExecution`, `WorkflowTask`, `WorkflowDynamicFlow`), all event classes

---

### 1.2 `WorkflowDispatcher` (169 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/WorkflowDispatcher.php`

**Class:** `WorkflowDispatcher`

**What it does:** Creates a new workflow instance and seeds the first execution token. This is the **entry point** for all triggering:
1. Resolves the execution plan (from published version or raw definition for dynamic flows)
2. Finds the trigger node key from the plan
3. Checks admission control (per-tenant concurrent-instance cap)
4. In a single DB transaction: creates `WorkflowInstance` row, creates `WorkflowNodeExecution` row for the trigger node, dispatches `ExecuteNodeJob` (only if admitted), increments `total_runs` on the workflow
5. After commit: broadcasts `InstanceStarted`, forwards to parent channel if child instance

If at capacity, creates the instance as `Pending` withholds the job -- `AdmitPendingInstancesCommand` promotes later.

**Dependencies:** `ExecutionPlanCompiler`, `NodeExecutorRegistry`, `InstanceAdmissionService`, `WorkflowManagementService` (via `WorkflowInstance` model)

---

### 1.3 `ExecutionPlanCompiler` (38 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/ExecutionPlanCompiler.php`

**Class:** `ExecutionPlanCompiler`

**What it does:** Turns a raw workflow definition array into an immutable `ExecutionPlan` object. For published versions (immutable definitions), the normalized form is cached forever by version ID using `Cache::rememberForever()`. Uses `WorkflowDefinitionNormalizer` to normalize the definition first.

**Dependencies:** `WorkflowDefinitionNormalizer`, `Cache`, `WorkflowVersion`

---

### 1.4 `ExecutionPlan` (200 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/ExecutionPlan.php`

**Class:** `ExecutionPlan`

**What it does:** Immutable, execution-shaped view of a workflow definition. Precomputes:
- Nodes indexed by key
- Outgoing/incoming adjacency lists (as `PlanEdge` objects, sorted by sort order)
- Entry points (trigger node or structural entries with no incoming edges)
- Join specs for merge nodes (parallel vs conditional, expected count, timeout)
- Fork branches
- Terminal node detection

Reuses `WorkflowDefinitionGraph` for traversal. This is the primary data structure the runtime queries during execution.

**Dependencies:** `WorkflowDefinitionGraph`, `PlanEdge`, `JoinSpec`

---

### 1.5 `PlanEdge` (52 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/PlanEdge.php`

**Class:** `PlanEdge` (final, readonly)

**What it does:** Immutable value object representing a single workflow edge. Properties: `id`, `source`, `target`, `branchType`, `conditionExpression`, `isDefaultBranch`, `joinNodeKey`, `sortOrder`. Has helpers: `isConditional()`, `isParallel()`, `isErrorEdge()` (always returns false currently).

---

### 1.6 `JoinSpec` (25 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/JoinSpec.php`

**Class:** `JoinSpec` (final, readonly)

**What it does:** Value object defining the synchronization contract for a merge node. Constants: `MODE_PARALLEL` (wait-all) and `MODE_CONDITIONAL` (first-arrival). Properties: `mode`, `expectedCount`, `timeoutSeconds`. Precomputed at plan-compile time so runtime never counts branches during execution (avoiding race conditions).

---

### 1.7 `NodeExecutionContext` (121 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/NodeExecutionContext.php`

**Class:** `NodeExecutionContext`

**What it does:** Read/write view given to each `NodeExecutor` for one execution. Provides:
- Access to `instance`, `execution`, `plan`, `nodeKey`, `nodeType`, `attempt`, `idempotencyKey`
- **`resolutionScope()`**: Returns `['context' => ..., 'trigger' => ..., 'input' => ...]` -- the three data namespaces
- **`evaluate(expr)`** / **`evaluateBoolean(expr)`**: Delegates to `ExpressionEvaluator` against the scope
- **`render(template)`**: Delegates to `TemplateInterpolator` against the scope
- **`setContextValue()`** / **`mergeContext()`**: Buffer mutations to instance context (persisted by runtime on commit)

**Dependencies:** `WorkflowInstance`, `WorkflowNodeExecution`, `ExecutionPlan`, `ExpressionEvaluator`, `TemplateInterpolator`

---

### 1.8 `NodeExecutionResult` (76 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/NodeExecutionResult.php`

**Class:** `NodeExecutionResult` (final)

**What it does:** Value object returned by every executor. Private constructor, named factory methods:
- **`proceed(edges, output)`** -- succeed and follow given edges
- **`branch(edge, output)`** -- succeed and follow exactly one selected edge
- **`wait(until, type, reason)`** -- park on durable wait
- **`fail(error, retryable)`** -- node failed
- **`terminate(output)`** -- consume token (branch end)
- **`noop()`** -- do nothing (e.g., a non-final join arrival)

The runtime's `applyResult()` interprets the `ResultKind` to advance, park, retry, or finish.

---

### 1.9 `NodeExecutorRegistry` (48 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/NodeExecutorRegistry.php`

**Class:** `NodeExecutorRegistry`

**What it does:** Registry mapping node-type strings to `NodeExecutor` implementations. Registered in `WorkflowsServiceProvider::register()`. Supports `register()` (auto-keyed by executor type), `registerAs(type, executor)` (custom key for multi-type executors like merge), `for()` (lookup, throws `UnsupportedNodeTypeException` on miss).

---

### 1.10 `RetryPolicy` (29 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/RetryPolicy.php`

**Class:** `RetryPolicy`

**What it does:** Configurable retry logic. `shouldRetry(attempt)`: checks against `max_attempts` (default 3). `delaySeconds(attempt)`: exponential backoff from `base_delay` (5s) capped at `max_delay` (300s). Strategy configurable between 'exponential' and linear.

---

### 1.11 `FailureClassifier` (35 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/FailureClassifier.php`

**Class:** `FailureClassifier`

**What it does:** Classifies exceptions as retryable (default) or non-retryable. Non-retryable classes: `ValidationException`, `UnsupportedNodeTypeException`, `InvalidArgumentException`, `LogicException`. Defaults to retryable (transient assumption is safer).

---

### 1.12 `InstanceAdmissionService` (29 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Admission/InstanceAdmissionService.php`

**Class:** `InstanceAdmissionService`

**What it does:** Per-tenant backpressure gate. Counts Running+Waiting instances for the tenant, returns true if under `max_concurrent_instances_per_tenant` (default 20). New instances above the cap go to `Pending` status.

---

### 1.13 `ExpressionEvaluator` (362 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Expression/ExpressionEvaluator.php`

**Class:** `ExpressionEvaluator`

**What it does:** Recursive-descent parser/evaluator for the boolean/comparison expression language. Sandboxed -- only operators `&& || == != >= <= > < !`, parentheses, and literals. No PHP eval. Identifiers resolve as dot-paths against data (e.g., `context.age`, `trigger.email`). Missing identifiers resolve to `null`. Supports short-circuit evaluation. Mirrors `ExpressionLanguageValidator` grammar exactly ("validated = evaluable").

---

### 1.14 `TemplateInterpolator` (69 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Expression/TemplateInterpolator.php`

**Class:** `TemplateInterpolator`

**What it does:** Interpolates `{{ expression }}` placeholders in action config strings (e.g., email subject/body). Each placeholder is evaluated by `ExpressionEvaluator`. Unresolvable placeholders render as empty string (never breaks rendering). Logs warnings for unresolved/error cases.

---

### 1.15 `NullAiContentGenerator` (17 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/NullAiContentGenerator.php`

**Class:** `NullAiContentGenerator` (implements `AiContentGenerator`)

**What it does:** Stub AI provider. Returns empty string. Used when no real AI service is configured. Bound in the service provider with the ability to swap for a real implementation.

---

### 1.16 `UnsupportedNodeTypeException` (13 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Exceptions/UnsupportedNodeTypeException.php`

**Class:** `UnsupportedNodeTypeException extends RuntimeException`

**What it does:** Thrown by `NodeExecutorRegistry::for()` when no executor is registered for a given node type.

---

## 2. CONTRACTS

### 2.1 `NodeExecutor` (22 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Contracts/NodeExecutor.php`

**Interface** with three methods:
- `type(): string` -- the node type key (e.g., 'send-email')
- `category(): NodeCategory` -- drives queue routing and timeout
- `execute(NodeExecutionContext): NodeExecutionResult`

### 2.2 `AiContentGenerator` (11 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Contracts/AiContentGenerator.php`

**Interface** with one method: `generate(string $prompt, array $config): string`

---

## 3. NODE EXECUTORS (`Services/Execution/Executors/`)

### 3.1 `ManualTriggerExecutor` (46 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/ManualTriggerExecutor.php`
- **Type:** `manual-trigger`, **Category:** Trigger
- Seeds instance context from HTTP trigger payload and static `config.variables`. Returns `proceed` with all outgoing edges.

### 3.2 `FormTriggerExecutor` (35 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/FormTriggerExecutor.php`
- **Type:** `form-trigger`, **Category:** Trigger
- Merges form data into instance context. Returns `proceed` with outgoing edges.

### 3.3 `WebhookTriggerExecutor` (29 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/WebhookTriggerExecutor.php`
- **Type:** `webhook-trigger`, **Category:** Trigger
- Simplest trigger -- passes webhook payload as output, proceeds.

### 3.4 `IfNodeExecutor` (74 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/IfNodeExecutor.php`
- **Type:** `if-node`, **Category:** Logic
- Evaluates `conditionExpression` from config. Partitions outgoing edges into yes-branch (`branchType: 'true'` or `'branch_1'`) and no-branch (else/default). Returns `branch()` for the selected edge. Graceful fallback: if only one edge, always takes it.

### 3.5 `SwitchNodeExecutor` (65 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/SwitchNodeExecutor.php`
- **Type:** `switch`, **Category:** Logic
- Resolves a variable from config via `evaluate()` (not `render()`). Matches against edge `branchType` values. Also supports per-edge `conditionExpression` as advanced override. Falls back to default edge.

### 3.6 `ForkNodeExecutor` (31 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/ForkNodeExecutor.php`
- **Type:** `and-node`, **Category:** Logic
- Fan-out: returns `proceed()` with all outgoing edges. Each parallel edge must carry a `join_node_key`. The runtime's `arriveAtMerge()` handles the merge coordination.

### 3.7 `MergeNodeExecutor` (38 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/MergeNodeExecutor.php`
- **Type:** `merge-and` (also registered for `merge-or` and `merge`), **Category:** Logic
- By the time this runs, the merge gating logic (arrive-count tracking) already happened in `WorkflowRuntime::arriveAtMerge()`. This simply proceeds to outgoing edges.

### 3.8 `TerminationNodeExecutor` (26 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/TerminationNodeExecutor.php`
- **Type:** `termination-node`, **Category:** Logic
- Returns `terminate()` -- consumes the token and completes the instance.

### 3.9 `SendEmailExecutor` (65 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/SendEmailExecutor.php`
- **Type:** `send-email`, **Category:** Action
- Renders `to`, `subject`, `body`, `cc`, `bcc` templates. Generates a deterministic `Message-ID` from the idempotency key (at-least-once dedup). Looks up the tenant's Google integration connection. Sends via `GmailClient`. Returns sent_to, message_id, gmail_id, thread_id.

### 3.10 `AiGeneratorExecutor` (42 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/AiGeneratorExecutor.php`
- **Type:** `ai-generator`, **Category:** Ai
- Renders the prompt from config, calls `AiContentGenerator::generate()`, stores result in instance context under `outputVariable` (default `ai_output`).

### 3.11 `TaskNodeExecutor` (94 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/TaskNodeExecutor.php`
- **Type:** `task-node`, **Category:** Action
- **Three-path re-entrant pattern:**
  1. **Initial creation:** Creates `WorkflowTask` row with assignee, title, description, input schema, due date. Returns `wait()` with `WaitType::TaskSla`.
  2. **Resume (task completed/expired):** Finds existing task, merges response into context, proceeds.
  3. **SLA breach:** Timer fired but task still open -- marks expired, proceeds with null response.
- Assignee resolved from `assignTo` config via template rendering.

### 3.12 `SubWorkflowExecutor` (136 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/SubWorkflowExecutor.php`
- **Type:** `sub-workflow`, **Category:** Flows
- **Three-path re-entrant pattern:**
  1. **Initial creation:** Validates child workflow (exists, published, same tenant). Evaluates `inputMapping` templates. Dispatches child via `WorkflowDispatcher`. Returns `wait()`.
  2. **Resume (child terminal):** Merges child context into parent under `outputVariable`. Proceeds.
  3. **Re-park (child still running):** Re-parks with `WaitType::SubWorkflow`.

### 3.13 `DynamicFlowExecutor` (148 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/DynamicFlowExecutor.php`
- **Type:** `dynamic-flow`, **Category:** Flows
- **Three-entry re-entrant pattern:**
  1. **Initial:** Creates `WorkflowDynamicFlow` row with `AwaitingDesign` status, pauses the parent instance (`Paused` status), writes a `WorkflowEvent`, returns `wait(WaitType::DynamicFlowDesign)`.
  2. **Awaiting design:** Re-parks.
  3. **Executing + child terminal:** Merges child output, marks dynamic flow completed, proceeds. If child failed, propagates failure.
- This enables a Manager/Creator to design a sub-flow at runtime that runs within the paused parent instance.

### 3.14 `DynamicEntryExecutor` (41 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Executors/DynamicEntryExecutor.php`
- **Type:** `dynamic-entry`, **Category:** Trigger
- Entry point for dynamically-designed sub-flows. Seeds context from payload (parent context passed by `DynamicFlowController`). Proceeds.

---

## 4. QUEUE JOB

### 4.1 `ExecuteNodeJob` (49 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Jobs/ExecuteNodeJob.php`

**Class:** `ExecuteNodeJob implements ShouldQueue`

**What it does:** The bridge between the queue and the runtime. Picks up a single `node_execution_id` from the queue and calls `WorkflowRuntime::advance()`. `$tries = 1` because retry logic lives inside the runtime (creates new execution rows with idempotency keys). Queue-level retries would bypass idempotency. Timeout varies by `NodeCategory`: Ai=120s, Action=60s, Logic=30s. On unhandled exception, calls `handleAdvanceFailure()` to ensure child instances are marked failed and parents are woken.

---

## 5. EVENTS (11 classes)

All events implement `ShouldBroadcastNow` and broadcast on private channels `workflow-instance.{instanceId}`.

| Class | Lines | Event Name | Purpose |
|---|---|---|---|
| `InstanceStarted` | 31 | `instance.started` | Instance created and running |
| `InstanceCompleted` | 32 | `instance.completed` | Instance finished successfully (includes final context) |
| `InstanceFailed` | 33 | `instance.failed` | Instance failed (includes error + context) |
| `InstanceCancelled` | 31 | `instance.cancelled` | Operator cancelled |
| `InstancePaused` | 34 | `instance.paused` | Instance paused (includes reason, e.g. `dynamic_flow`) |
| `NodeStarted` | 42 | `node.started` | Node execution begins (includes node_key, type, attempt, input) |
| `NodeCompleted` | 44 | `node.completed` | Node finished (includes output) |
| `NodeFailed` | 44 | `node.failed` | Node failed (includes error, `will_retry` flag) |
| `NodeWaiting` | 44 | `node.waiting` | Node parked on durable wait (includes wait_type, wait_until) |
| `NodeRetrying` | 39 | `node.retrying` | New retry execution created (includes attempt + failed_attempt) |
| `ChildInstanceEventForwarded` | 38 | `child.{original_event}` | Re-broadcasts any child event on the parent's channel |

Events are dispatched via `DB::afterCommit()` to prevent clients from seeing status over websocket before it's visible via the API (or seeing a rolled-back status).

---

## 6. ENUMS (11 classes)

| Enum | File | Cases | Key Methods |
|---|---|---|---|
| `ResultKind` | `app/Enums/ResultKind.php` (13 lines) | `Proceed`, `Branch`, `Wait`, `Fail`, `Terminate`, `Noop` | -- |
| `NodeExecutionStatus` | `app/Enums/NodeExecutionStatus.php` (35 lines) | `Pending`, `Running`, `Succeeded`, `Failed`, `Waiting`, `Skipped`, `Consumed` | `isLive()`, `isRunnable()`, `isTerminal()` |
| `WorkflowInstanceStatus` | `app/Enums/WorkflowInstanceStatus.php` (19 lines) | `Pending`, `Running`, `Waiting`, `Paused`, `Completed`, `Failed`, `Cancelled` | `isTerminal()` |
| `TriggerType` | `app/Enums/TriggerType.php` (13 lines) | `Manual`, `Form`, `Webhook`, `SubWorkflow` | -- |
| `NodeCategory` | `app/Enums/NodeCategory.php` (12 lines) | `Trigger`, `Logic`, `Ai`, `Flows`, `Action` | -- |
| `WaitType` | `app/Enums/WaitType.php` (11 lines) | `TaskSla`, `MergeTimeout`, `SubWorkflow`, `DynamicFlowDesign` | -- |
| `EdgeBranchType` | `app/Enums/EdgeBranchType.php` (10 lines) | `Default`, `Conditional`, `Parallel` | -- |
| `DynamicFlowStatus` | `app/Enums/DynamicFlowStatus.php` (16 lines) | `AwaitingDesign`, `Executing`, `Completed`, `Cancelled` | `isTerminal()` |
| `WorkflowStatus` | `app/Enums/WorkflowStatus.php` (10 lines) | `Active`, `Disabled`, `Deleted` | -- |
| `VerificationMode` | `app/Enums/VerificationMode.php` (9 lines) | `Full`, `Segment` | -- |
| `NodeConfigFieldType` | `app/Enums/NodeConfigFieldType.php` (17 lines) | `TEXT`, `TEXTAREA`, `SELECT`, `TOGGLE`, `NUMBER`, `TAGS`, `JSON`, `EMAIL`, `BRANCHES`, `READONLY` | -- |

---

## 7. CONTROLLERS (7 files)

### 7.1 `WorkflowController` (182 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/WorkflowController.php`
- **CRUD + publish + trigger webhook**: `index`, `store`, `show`, `updateDraft`, `publish`, `versions`, `updateStatus`, `destroy`, `purge`, `triggerWebhook`, `templates`, `proposal`, `validateDefinition`
- Delegates to `WorkflowManagementService` for all business logic

### 7.2 `WorkflowTriggerController` (34 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/WorkflowTriggerController.php`
- `manual(Request, Workflow)` and `form(Request, Workflow)` -- authenticated trigger endpoints
- Returns `202 Accepted` with instance_id + status

### 7.3 `WorkflowInstanceController` (180 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/WorkflowInstanceController.php`
- `index`: Paginated list with status/date filters
- `show`: Full instance with node executions, dynamic flows, child instances
- `failures`: Failed node executions for an instance
- `cancel`: Delegates to `WorkflowRuntime::cancel()`
- `retryFromNode`: Delegates to `WorkflowRuntime::retryFromNode()`
- Tenant-scoped authorization on all endpoints

### 7.4 `WorkflowTaskController` (302 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/WorkflowTaskController.php`
- `summary`: Pending + overdue counts for home screen
- `index`: Task inbox with role-based scoping (BusinessOwner=tenant-wide, Manager=team, Employee=self-only), search, sort, status filtering
- `show`: Full task detail with input schema
- `saveDraft`: Persist partial response without closing task
- `submit`: Validates against `input_schema`, marks completed, **resumes the parked execution** by transitioning `Waiting -> Pending` and dispatching `ExecuteNodeJob`
- `validateAgainstSchema()`: Dynamic Laravel validation rules generated from task's `input_schema`

### 7.5 `DynamicFlowController` (182 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/DynamicFlowController.php`
- `show`: Returns dynamic flow metadata + parent definition + parent context for design UI
- `storeDefinition`: Validates the user-designed sub-definition (with segment verification), dispatches child via `WorkflowDispatcher`, updates dynamic flow status to `Executing`, resumes parent instance, writes audit trail
- Authorization: only workflow creator or team manager

### 7.6 `PublicFormController` (49 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/PublicFormController.php`
- Unauthenticated, throttled endpoints (30 requests/minute)
- `show`: Returns form schema for a public token
- `submit`: Validates submission, dispatches via `PublicFormService`
- Deliberately does not return instance_id/status (anonymous caller should not see internal execution details)

### 7.7 `NodeController` (44 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/NodeController.php`
- `index`: Lists all active node types with their config fields for the canvas

---

## 8. API ROUTES

**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/routes/api.php` (71 lines)

Three route groups:
1. **`broadcasting/auth`** -- JWT-authenticated broadcasting auth (for private channels)
2. **`v1/public/forms/{publicToken}`** -- Unauthenticated, throttled public form endpoints
3. **`v1/workflows`** (auth:api + active.user middleware) -- All authenticated endpoints:
   - Static routes: `/nodes`, `/validate`, `/templates`, `/proposals/ai`
   - Instance routes: `/instances/{instance}`, `/instances/{instance}/failures`, `/instances/{instance}/cancel`, `/instances/{instance}/retry-from-node`
   - Dynamic flow: `/instances/{instance}/dynamic-flow`, `/instances/{instance}/dynamic-flow/definition`
   - Tasks: `/tasks/summary`, `/tasks`, `/tasks/{task}`, `PATCH /tasks/{task}/draft`, `POST /tasks/{task}/submit`
   - CRUD: `GET/POST /`, `GET/PATCH/DELETE /{workflow}`, `PATCH /{workflow}/draft`, `POST /{workflow}/publish`, etc.
   - Triggers: `POST /{workflow}/trigger/webhook`, `POST /{workflow}/trigger/manual`, `POST /{workflow}/trigger/form`
   - Instance list: `GET /{workflow}/instances`

---

## 9. SUPPORTING SERVICES (non-verification)

### 9.1 `WorkflowManagementService` (777 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowManagementService.php`

Orchestrates CRUD + publish + trigger with full role-based authorization. Key methods:
- `triggerWebhook()`, `triggerManual()`, `triggerForm()` -- validate workflow status, delegate to `WorkflowDispatcher`
- `publish()` -- validates definition, detects change type (structural/config/none), creates version with semantic versioning
- `updateDraft()` -- optimistic locking via `expected_draft_revision`
- `serializeWorkflow()` -- serializes workflow for API response including actions available to the caller
- Role-based visibility: BusinessOwner sees all, Manager sees team workflows, Employee sees team membership workflows

### 9.2 `PublicFormService` (158 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/PublicFormService.php`

Unauthenticated public form service. Eligibility: published version + Active status + form-trigger with `accessLevel === 'public'`. Returns form schema, validates submissions, dispatches via `WorkflowDispatcher`. Uses UUID public tokens. Returns 404 without leaking reason (intentional security measure).

### 9.3 `WorkflowDefinitionValidator` (21 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowDefinitionValidator.php`

Thin facade over `WorkflowVerificationService::verify()`. Accepts definition, optional workflow/actor, verification mode.

### 9.4 `NodeDefinitionService` (18 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/NodeDefinitionService.php`

Simple query service: lists active `Node` records with their `configFields`.

---

## 10. ARTISAN COMMANDS (3 classes)

### 10.1 `ScanWorkflowTimersCommand` (74 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Console/Commands/ScanWorkflowTimersCommand.php`
- Runs every minute (`workflows:scan-timers`)
- Promotes `Waiting` executions whose `wait_until` has passed back to `Pending` and re-dispatches their job
- Handles merge timeouts and task SLA expirations
- Uses `chunkById(100)` for memory-safe processing

### 10.2 `AdmitPendingInstancesCommand` (83 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Console/Commands/AdmitPendingInstancesCommand.php`
- Runs every minute (`workflows:admit-pending`)
- Promotes `Pending` instances to `Running` when the tenant cap allows it
- Processes oldest-first for FIFO ordering
- Dispatches the withheld trigger-node job

### 10.3 `ExpireOverdueInstancesCommand` (64 lines)
**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Console/Commands/ExpireOverdueInstancesCommand.php`
- Runs daily (`workflows:expire-overdue`)
- Fails instances that have exceeded `max_instance_duration` (default 30 days)
- Consumes all pending/running/waiting tokens to stop timer scans

---

## 11. MODELS (execution-relevant)

### 11.1 `WorkflowInstance` (106 lines)
- **Table:** `workflow_instances`
- Status: `WorkflowInstanceStatus` enum (Pending/Running/Waiting/Paused/Completed/Failed/Cancelled)
- JSON columns: `payload`, `context`, `error`
- Supports parent-child hierarchy: `parent_instance_id`, `parent_execution_id`
- Dynamic flow support: `is_dynamic`, `dynamic_flow_id`
- Relationships: `workflow`, `workflowVersion`, `nodeExecutions`, `tasks`, `events`, `dynamicFlow(s)`, `childInstances`

### 11.2 `WorkflowNodeExecution` (86 lines)
- **Table:** `workflow_node_executions`
- The unified runtime-state record -- a non-terminal row IS an active execution token
- Status: `NodeExecutionStatus` enum
- JSON columns: `input`, `output`, `error`
- Fork-join support: `expected_count`, `arrived_count`, `fork_group`
- Wait support: `wait_until`, `wait_type`
- Idempotency: `idempotency_key` (hash of instance+node+attempt)
- Relationships: `instance`, `parent`/`children`, `task`

### 11.3 `WorkflowTask` (86 lines)
- **Table:** `workflow_tasks`
- Human-facing projection of a parked `task-node`
- Status: open/completed/expired/cancelled (string, not enum)
- JSON columns: `input_schema`, `response`, `draft_response`
- `displayStatus()` method: overdue open tasks show as "expired" without changing underlying status
- Relationships: `instance`, `execution`, `assignee`, `completedBy`

---

## 12. SERVICE PROVIDER WIRING

**File:** `/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Providers/WorkflowsServiceProvider.php` (138 lines)

Registers:
- All execution services as singletons
- `AiContentGenerator -> NullAiContentGenerator` binding (swappable)
- 14 executor registrations via `NodeExecutorRegistry`, including `MergeNodeExecutor` registered under 3 keys (`merge-and`, `merge-or`, `merge`)
- 3 scheduled commands: `scan-timers` (minute), `admit-pending` (minute), `expire-overdue` (daily)
- Broadcasting channel authorization: `workflow-instance.{id}` gated by tenant match

---

## COMPLETE EXECUTION FLOW SUMMARY

```
1. TRIGGER (HTTP/Webhook/Form)
   WorkflowTriggerController / PublicFormController
       -> WorkflowManagementService::trigger*()
           -> WorkflowDispatcher::dispatch()
               1. Resolve ExecutionPlan from published version
               2. Check InstanceAdmissionService::canAdmit()
               3. Create WorkflowInstance row (Running or Pending)
               4. Create WorkflowNodeExecution row for trigger node
               5. Dispatch ExecuteNodeJob to queue (if admitted)
               6. Broadcast InstanceStarted

2. EXECUTION LOOP (Queue Worker)
   ExecuteNodeJob::handle()
       -> WorkflowRuntime::advance($executionId)
           1. claim() -- lockForUpdate, transition to Running
           2. resolvePlan() -- compile from version or dynamic flow
           3. Build NodeExecutionContext
           4. Broadcast NodeStarted
           5. registry->for(type)->execute(context) -- run executor
           6. Catch exceptions, classify retryability
           7. DB::transaction: lock, applyResult()
               - onSucceed: store output, seed successors via seedExecution()
               - onTerminate: complete instance
               - onWait: park token
               - onFail: retry or fail instance
               - onConsume: mark consumed

3. FAN-OUT (successor dispatching)
   WorkflowRuntime::seedExecution()
       - If merge node: arriveAtMerge() (lock, increment, dispatch when threshold met)
       - Otherwise: create child WorkflowNodeExecution, dispatch ExecuteNodeJob

4. HUMAN TASK RESUMPTION
   WorkflowTaskController::submit()
       -> Mark task completed
       -> resumeExecution(): transition Waiting -> Pending, dispatch ExecuteNodeJob

5. SUB-WORKFLOW RESUMPTION
   WorkflowRuntime::wakeParentExecution()
       -> Find parent execution in Waiting state
       -> Transition to Pending, dispatch ExecuteNodeJob

6. TIMER SCANNING
   ScanWorkflowTimersCommand (every minute)
       -> Find Waiting executions past wait_until
       -> Transition to Pending, dispatch ExecuteNodeJob

7. ADMISSION CONTROL
   AdmitPendingInstancesCommand (every minute)
       -> Find Pending instances under tenant cap
       -> Transition to Running, dispatch ExecuteNodeJob

8. INSTANCE EXPIRY
   ExpireOverdueInstancesCommand (daily)
       -> Fail instances past max_instance_duration
       -> Consume all remaining tokens
```

---

## TOTAL FILE AND LINE COUNTS

| Category | Files | Total Lines |
|---|---|---|
| Execution Core (Runtime, Dispatcher, Plan, etc.) | 12 | 1,550 |
| Execution Contracts | 2 | 33 |
| Execution Executors | 14 | 832 |
| Execution Expression | 2 | 431 |
| Execution Exceptions | 1 | 13 |
| Execution Admission | 1 | 29 |
| Queue Jobs | 1 | 49 |
| Events | 11 | 382 |
| Enums | 11 | 153 |
| Controllers | 7 | 973 |
| Routes | 1 | 71 |
| Non-verification Services | 4 | 974 |
| Artisan Commands | 3 | 221 |
| Models (execution-relevant) | 3 | 278 |
| Resources | 3 | 199 |
| Service Provider | 1 | 138 |
| **TOTAL** | **68** | **~6,325** |
</task_result>
</task>
```

---

## Assistant (Build · Big Pickle · 25.7s)

_Thinking:_

Now I have a comprehensive understanding of the entire workflow module. Let me synthesize this into a clear, actionable architectural review. Let me identify the key issues:

1. **Verification layer is over-engineered** - 33 files, 3,554 lines,8 sequential rules + 10 node-type rules, with significant indirection
2. **WorkflowDefinitionValidator** is a pointless wrapper (21 lines, pure pass-through)
3. **WorkflowVerificationService** has 18 constructor dependencies - a clear sign of over-injection
4. **ExpressionLanguageValidator** duplicates grammar with **ExpressionEvaluator** - one for validation, one for runtime. This is a duplication of the expression grammar.
5. **WorkflowManagementService** is 777 lines - god class territory
6. **WorkflowRuntime** is 675 lines - also large but arguably necessary for the complexity it manages
7. **VariableAvailability trait** only works with manual-trigger - functional gap
8. **DB queries during verification** - Node definitions queried every time, should be cached
9. **Missing interfaces** - WorkflowVerificationService has no contract

Let me organize my findings into a clear review.

Now I have a complete picture. Here's my architectural review.

---

# Workflow Module Architecture Review

## Summary

The module is ~10,000 lines of PHP across ~100 files. The core execution engine is well-designed (token-based, event-sourced, crash-resilient). The main problems are in the **verification layer** (over-engineered, too much indirection) and a couple of **god classes** in the service layer. Below are the specific issues, ranked by impact.

---

## 1. `WorkflowDefinitionValidator` — Dead Indirection

**File:** `Modules/Workflows/app/Services/WorkflowDefinitionValidator.php` (21 lines)

This class does nothing. It wraps `WorkflowVerificationService::verify()` and calls `->toArray()`. Every caller could just inject `WorkflowVerificationService` directly. This adds cognitive overhead for zero value.

**Recommendation:** Delete it. Update the 2-3 callers to use `WorkflowVerificationService` directly.

---

## 2. Constructor Injection Explosion in `WorkflowVerificationService` (18 dependencies)

The service injects all 8 top-level rules AND all 10 node-type rules individually, then manually registers the 10 node-type rules into `NodeTypeVerificationRule` in its constructor. This is the classic "list injection" smell.

**Recommendation:** Use Laravel's tagged bindings. Tag all `NodeTypeRule` implementations, inject them as `iterable<NodeTypeRule>` into `NodeTypeVerificationRule`, and let it self-register. This cuts `WorkflowVerificationService`'s constructor from 18 to ~10.

---

## 3. Duplicate Expression Grammars

Two independent recursive-descent parsers implement the same boolean expression language:

| Class | Lines | Purpose |
|---|---|---|
| `ExpressionLanguageValidator` | 241 | Design-time validation |
| `ExpressionEvaluator` | 362 | Runtime evaluation |

They share the same grammar (`&&` > `||` > comparison > unary > primary) but are implemented independently. If the grammar evolves, both must be updated in lockstep.

**Recommendation:** Extract a shared `ExpressionGrammar` / token layer. The validator and evaluator should be two faces of the same parser — tokenize + parse once, then either validate types or evaluate against data.

---

## 4. `WorkflowManagementService` — 777-line God Class

This class handles CRUD, publish, trigger orchestration, role-based visibility, version comparison, and serialization. It has too many responsibilities.

**Recommendation:** Split into at least three services:
- `WorkflowCrudService` — create, update draft, delete, status
- `WorkflowPublishService` — validation, versioning, change detection
- `WorkflowTriggerService` — orchestrate triggers, delegate to dispatcher

---

## 5. `WorkflowRuntime` — 675 lines (borderline)

This is the heart of the execution engine. It's large but arguably justified by the complexity of the token-based execution loop, fork-join coordination, and retry logic. The five `on*` callback methods (`onSucceed`, `onTerminate`, `onWait`, `onFail`, `onConsume`) are a natural decomposition.

**Recommendation:** Extract the fork-join merge coordination (`arriveAtMerge` and related logic, ~80 lines) into a dedicated `MergeCoordinator` class. The runtime should only call `$mergeCoordinator->arrive(...)`. This is the one piece that can cleanly separate.

---

## 6. DB Queries During Verification — No Caching

`SyntaxVerificationRule` queries `Node::query()->where('is_active', true)->with('configFields')->get()` on **every single verification call**. Node definitions are seeded data that virtually never changes. Same issue with `ContextualVerificationRule` querying users/memberships per call.

**Recommendation:** Cache node definitions with `Cache::rememberForever()` (they're only changed by seeders). For contextual queries, batch them instead of N+1 per node.

---

## 7. `ExpressionLanguageValidator` is Stateful (Not Reentrant)

The validator stores `$tokens`, `$position`, `$variables` as instance properties. If injected as a singleton and called concurrently, it breaks. It should be either a pure function or instantiated per-call.

**Recommendation:** Make `validateBoolean()` create a local state object (or use the shared grammar layer from point 3). Stateless is safer.

---

## 8. `VariableAvailability` Trait Only Supports `manual-trigger`

The trait's `collectAvailableContextKeys()` returns `null` for any trigger type other than `manual-trigger`. This means form-trigger workflows get no variable-availability checking — a functional gap that could let invalid variable references slip through validation.

**Recommendation:** Add form-trigger context resolution (form field keys become available variables). This is a feature gap, not just cleanup.

---

## 9. `DataFlowVerificationRule` Duplicates `normalizeVariable` Logic

Both `DataFlowVerificationRule::normalizeVariable()` and `DataFlowAnalyzer::normalizeKey()` do the same `context.` prefix normalization. 

**Recommendation:** Extract a single `VariableNormalizer` utility or put the normalization on `DataFlowAnalyzer` and have the rule delegate to it.

---

## 10. No Interface for `WorkflowVerificationService`

The orchestrator has no contract. `WorkflowDefinitionValidator` and all callers depend directly on the concrete class, making testing and swapping harder.

**Recommendation:** Extract a `VerificationService` interface with `verify()` and `validate()` methods.

---

## What's Actually Good

- **Execution engine** (`WorkflowRuntime`, `WorkflowDispatcher`, `ExecutionPlan`) — clean token-based design, crash-resilient, well-decomposed
- **`NodeExecutor` interface + registry** — textbook plugin architecture, easy to add new node types
- **`NodeExecutionContext`** — clean separation between runtime state and executor logic
- **`NodeExecutionResult`** — excellent use of named constructors (`proceed`, `branch`, `wait`, `fail`, `terminate`, `noop`)
- **Event broadcasting** — all 11 events broadcast on private channels with `DB::afterCommit()`, properly handling the timing issue
- **Artisan commands** — timer scanning, admission control, and expiry are cleanly separated
- **`WorkflowDefinitionGraph`** — solid graph abstraction shared across all verification rules

---

## Priority Order

| Priority | Issue | Effort | Impact |
|---|---|---|---|
| 1 | Delete `WorkflowDefinitionValidator` | 10 min | Removes confusion |
| 2 | Tag-based node-type rule injection | 30 min | Cuts constructor from 18→10 deps |
| 3 | Cache node definitions in verification | 20 min | Performance + correctness |
| 4 | Extract `MergeCoordinator` from runtime | 1 hr | Cleaner runtime |
| 5 | Split `WorkflowManagementService` | 2 hr | Maintainability |
| 6 | Shared expression grammar | 3 hr | Eliminates grammar drift risk |
| 7 | Fix `ExpressionLanguageValidator` statefulness | 30 min | Correctness under concurrency |
| 8 | Add form-trigger variable resolution | 1 hr | Functional completeness |
| 9 | Extract `VariableNormalizer` | 20 min | DRY |
| 10 | Add `VerificationService` interface | 15 min | Testability |

The first 3 items are quick wins. Items 5-6 are the most impactful for long-term maintainability. The rest are incremental improvements.

---

## User

please fix issues: 2, 3, 5

---

## Assistant (Build · Big Pickle · 16.4s)

---

## User

please fix issues: 2, 3, 5

---

## User

please fix issues: 2, 3, 5

---

## Assistant (Build · Big Pickle · 9.8s)

---

## User

please fix issues: 2, 3, 5

---

## Assistant (Build · Big Pickle · 3.5s)

---

## User

please fix issues: 2, 3, 5

---

## Assistant (Build · Big Pickle · 4.9s)

---

## User

please fix issues: 2, 3, 5

---

## Assistant (Build · Big Pickle · 17.5s)

---

## User

please fix issues: 2, 3, 5

---

## Assistant (Build · Big Pickle · 8.2s)

_Thinking:_

The user wants me to fix issues 2, 3, and 5 from my architectural review:

1. **Issue 2**: Constructor injection explosion in `WorkflowVerificationService` (18 dependencies) - Use tagged bindings for NodeTypeRules
2. **Issue 3**: Duplicate expression grammars - Extract shared expression grammar
3. **Issue 5**: `WorkflowManagementService` 777-line god class - Split into multiple services

Let me start by reading all the relevant files to understand the current state before making changes.

**Tool: todowrite**

**Input:**
```json
{
  "todos": [
    {
      "content": "Issue 2: Use tagged bindings for NodeTypeRules to reduce WorkflowVerificationService constructor from 18 to ~10 deps",
      "status": "pending",
      "priority": "high"
    },
    {
      "content": "Issue 3: Extract shared expression grammar from ExpressionLanguageValidator and ExpressionEvaluator",
      "status": "pending",
      "priority": "high"
    },
    {
      "content": "Issue 5: Split WorkflowManagementService into WorkflowCrudService, WorkflowPublishService, WorkflowTriggerService",
      "status": "pending",
      "priority": "high"
    }
  ]
}
```

**Output:**
```
[
  {
    "content": "Issue 2: Use tagged bindings for NodeTypeRules to reduce WorkflowVerificationService constructor from 18 to ~10 deps",
    "status": "pending",
    "priority": "high"
  },
  {
    "content": "Issue 3: Extract shared expression grammar from ExpressionLanguageValidator and ExpressionEvaluator",
    "status": "pending",
    "priority": "high"
  },
  {
    "content": "Issue 5: Split WorkflowManagementService into WorkflowCrudService, WorkflowPublishService, WorkflowTriggerService",
    "status": "pending",
    "priority": "high"
  }
]
```

---

## Assistant (Build · Big Pickle · 5.8s)

_Thinking:_

Let me read all the relevant files first.

Let me read all the files I need to modify.

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVerificationService.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVerificationService.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services;
4: 
5: use Modules\Auth\Models\User;
6: use Modules\Workflows\Enums\VerificationMode;
7: use Modules\Workflows\Models\Workflow;
8: use Modules\Workflows\Services\Verification\Rules\ContextualVerificationRule;
9: use Modules\Workflows\Services\Verification\Rules\DataFlowVerificationRule;
10: use Modules\Workflows\Services\Verification\Rules\ExpressionVerificationRule;
11: use Modules\Workflows\Services\Verification\Rules\FormTriggerVerificationRule;
12: use Modules\Workflows\Services\Verification\Rules\GraphControlFlowVerificationRule;
13: use Modules\Workflows\Services\Verification\Rules\NodeType\AiGeneratorNodeTypeRule;
14: use Modules\Workflows\Services\Verification\Rules\NodeType\DynamicEntryNodeTypeRule;
15: use Modules\Workflows\Services\Verification\Rules\NodeType\ForkNodeTypeRule;
16: use Modules\Workflows\Services\Verification\Rules\NodeType\IfNodeTypeRule;
17: use Modules\Workflows\Services\Verification\Rules\NodeType\MergeNodeTypeRule;
18: use Modules\Workflows\Services\Verification\Rules\NodeType\SendEmailNodeTypeRule;
19: use Modules\Workflows\Services\Verification\Rules\NodeType\SubWorkflowNodeTypeRule;
20: use Modules\Workflows\Services\Verification\Rules\NodeType\SwitchNodeTypeRule;
21: use Modules\Workflows\Services\Verification\Rules\NodeType\TaskNodeTypeRule;
22: use Modules\Workflows\Services\Verification\Rules\NodeType\TerminationNodeTypeRule;
23: use Modules\Workflows\Services\Verification\Rules\NodeTypeVerificationRule;
24: use Modules\Workflows\Services\Verification\Rules\StructuredControlFlowVerificationRule;
25: use Modules\Workflows\Services\Verification\Rules\SyntaxVerificationRule;
26: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
27: use Modules\Workflows\Services\Verification\WorkflowDefinitionNormalizer;
28: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
29: 
30: class WorkflowVerificationService
31: {
32:     public function __construct(
33:         protected WorkflowDefinitionNormalizer $normalizer,
34:         protected SyntaxVerificationRule $syntaxRule,
35:         protected GraphControlFlowVerificationRule $graphRule,
36:         protected StructuredControlFlowVerificationRule $structuredControlFlowRule,
37:         protected ExpressionVerificationRule $expressionRule,
38:         protected ContextualVerificationRule $contextualRule,
39:         protected NodeTypeVerificationRule $nodeTypeRule,
40:         protected DataFlowVerificationRule $dataFlowRule,
41:         protected FormTriggerVerificationRule $formTriggerRule,
42:         protected IfNodeTypeRule $ifNodeTypeRule,
43:         protected ForkNodeTypeRule $forkNodeTypeRule,
44:         protected SwitchNodeTypeRule $switchNodeTypeRule,
45:         protected MergeNodeTypeRule $mergeNodeTypeRule,
46:         protected TaskNodeTypeRule $taskNodeTypeRule,
47:         protected SendEmailNodeTypeRule $sendEmailNodeTypeRule,
48:         protected AiGeneratorNodeTypeRule $aiGeneratorNodeTypeRule,
49:         protected TerminationNodeTypeRule $terminationNodeTypeRule,
50:         protected SubWorkflowNodeTypeRule $subWorkflowNodeTypeRule,
51:         protected DynamicEntryNodeTypeRule $dynamicEntryNodeTypeRule,
52:     ) {
53:         $this->nodeTypeRule->register($this->ifNodeTypeRule);
54:         $this->nodeTypeRule->register($this->forkNodeTypeRule);
55:         $this->nodeTypeRule->register($this->switchNodeTypeRule);
56:         $this->nodeTypeRule->register($this->mergeNodeTypeRule);
57:         $this->nodeTypeRule->register($this->taskNodeTypeRule);
58:         $this->nodeTypeRule->register($this->sendEmailNodeTypeRule);
59:         $this->nodeTypeRule->register($this->aiGeneratorNodeTypeRule);
60:         $this->nodeTypeRule->register($this->terminationNodeTypeRule);
61:         $this->nodeTypeRule->register($this->subWorkflowNodeTypeRule);
62:         $this->nodeTypeRule->register($this->dynamicEntryNodeTypeRule);
63:     }
64: 
65:     public function verify(array $definition, ?Workflow $workflow = null, ?User $actor = null, VerificationMode $mode = VerificationMode::Full): WorkflowVerificationResult
66:     {
67:         $normalizedDefinition = $this->normalizer->normalize($definition);
68:         $graph = new WorkflowDefinitionGraph($normalizedDefinition);
69:         $result = new WorkflowVerificationResult;
70: 
71:         foreach ($this->rules() as $rule) {
72:             $rule->verify($normalizedDefinition, $graph, $result, $workflow, $actor, $mode);
73:         }
74: 
75:         return $result;
76:     }
77: 
78:     protected function rules(): array
79:     {
80:         return [
81:             $this->syntaxRule,
82:             $this->graphRule,
83:             $this->structuredControlFlowRule,
84:             $this->expressionRule,
85:             $this->contextualRule,
86:             $this->nodeTypeRule,
87:             $this->dataFlowRule,
88:             $this->formTriggerRule,
89:         ];
90:     }
91: }

(End of file - total 91 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeTypeVerificationRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeTypeVerificationRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules;
4: 
5: use Modules\Auth\Models\User;
6: use Modules\Workflows\Enums\VerificationMode;
7: use Modules\Workflows\Models\Workflow;
8: use Modules\Workflows\Services\Verification\Rules\NodeType\NodeTypeRule;
9: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
10: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
11: 
12: class NodeTypeVerificationRule implements VerificationRule
13: {
14:     /** @var array<string, NodeTypeRule> */
15:     protected array $rules = [];
16: 
17:     public function register(NodeTypeRule $rule): void
18:     {
19:         $this->rules[$rule->nodeType()] = $rule;
20:     }
21: 
22:     public function verify(
23:         array $definition,
24:         WorkflowDefinitionGraph $graph,
25:         WorkflowVerificationResult $result,
26:         ?Workflow $workflow = null,
27:         ?User $actor = null,
28:         VerificationMode $mode = VerificationMode::Full,
29:     ): void {
30:         foreach ($graph->nodes() as $index => $node) {
31:             $type = $node['type'] ?? '';
32: 
33:             if (isset($this->rules[$type])) {
34:                 $this->rules[$type]->verify($node, (int) $index, $graph, $result, $workflow);
35:             }
36:         }
37:     }
38: }

(End of file - total 38 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/NodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/NodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
7: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
8: 
9: interface NodeTypeRule
10: {
11:     public function nodeType(): string;
12: 
13:     public function verify(
14:         array $node,
15:         int $index,
16:         WorkflowDefinitionGraph $graph,
17:         WorkflowVerificationResult $result,
18:         ?Workflow $workflow = null,
19:     ): void;
20: }

(End of file - total 20 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Providers/WorkflowsServiceProvider.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Providers/WorkflowsServiceProvider.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Providers;
4: 
5: use Illuminate\Console\Scheduling\Schedule;
6: use Illuminate\Support\Facades\Broadcast;
7: use Illuminate\Support\ServiceProvider;
8: use Modules\Workflows\Console\Commands\AdmitPendingInstancesCommand;
9: use Modules\Workflows\Console\Commands\ExpireOverdueInstancesCommand;
10: use Modules\Workflows\Console\Commands\ScanWorkflowTimersCommand;
11: use Modules\Workflows\Models\WorkflowInstance;
12: use Modules\Workflows\Services\Execution\Admission\InstanceAdmissionService;
13: use Modules\Workflows\Services\Execution\Contracts\AiContentGenerator;
14: use Modules\Workflows\Services\Execution\ExecutionPlanCompiler;
15: use Modules\Workflows\Services\Execution\Executors\AiGeneratorExecutor;
16: use Modules\Workflows\Services\Execution\Executors\DynamicEntryExecutor;
17: use Modules\Workflows\Services\Execution\Executors\DynamicFlowExecutor;
18: use Modules\Workflows\Services\Execution\Executors\ForkNodeExecutor;
19: use Modules\Workflows\Services\Execution\Executors\FormTriggerExecutor;
20: use Modules\Workflows\Services\Execution\Executors\IfNodeExecutor;
21: use Modules\Workflows\Services\Execution\Executors\ManualTriggerExecutor;
22: use Modules\Workflows\Services\Execution\Executors\MergeNodeExecutor;
23: use Modules\Workflows\Services\Execution\Executors\SendEmailExecutor;
24: use Modules\Workflows\Services\Execution\Executors\SubWorkflowExecutor;
25: use Modules\Workflows\Services\Execution\Executors\SwitchNodeExecutor;
26: use Modules\Workflows\Services\Execution\Executors\TaskNodeExecutor;
27: use Modules\Workflows\Services\Execution\Executors\TerminationNodeExecutor;
28: use Modules\Workflows\Services\Execution\Executors\WebhookTriggerExecutor;
29: use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
30: use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
31: use Modules\Workflows\Services\Execution\FailureClassifier;
32: use Modules\Workflows\Services\Execution\NodeExecutorRegistry;
33: use Modules\Workflows\Services\Execution\NullAiContentGenerator;
34: use Modules\Workflows\Services\Execution\RetryPolicy;
35: use Modules\Workflows\Services\Execution\WorkflowDispatcher;
36: use Modules\Workflows\Services\Execution\WorkflowRuntime;
37: use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
38: use Modules\Workflows\Services\Verification\Rules\ContextualVerificationRule;
39: use Modules\Workflows\Services\Verification\Rules\ExpressionVerificationRule;
40: use Modules\Workflows\Services\Verification\Rules\GraphControlFlowVerificationRule;
41: use Modules\Workflows\Services\Verification\Rules\SyntaxVerificationRule;
42: use Modules\Workflows\Services\Verification\WorkflowDefinitionNormalizer;
43: use Modules\Workflows\Services\WorkflowManagementService;
44: use Modules\Workflows\Services\WorkflowVerificationService;
45: 
46: class WorkflowsServiceProvider extends ServiceProvider
47: {
48:     protected string $name = 'Workflows';
49: 
50:     public function boot(): void
51:     {
52:         $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
53:         $this->commands([
54:             ScanWorkflowTimersCommand::class,
55:             AdmitPendingInstancesCommand::class,
56:             ExpireOverdueInstancesCommand::class,
57:         ]);
58: 
59:         $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
60:             $schedule->command('workflows:scan-timers')->everyMinute()->withoutOverlapping();
61:             $schedule->command('workflows:admit-pending')->everyMinute()->withoutOverlapping();
62:             $schedule->command('workflows:expire-overdue')->daily()->withoutOverlapping();
63:         });
64: 
65:         $this->bootBroadcasting();
66:     }
67: 
68:     private function bootBroadcasting(): void
69:     {
70:         Broadcast::channel('workflow-instance.{instanceId}', function ($user, string $instanceId): bool {
71:             $instance = WorkflowInstance::find((int) $instanceId);
72: 
73:             return $instance !== null
74:                 && (int) $instance->tenant_id === (int) $user->tenant_id;
75:         });
76:     }
77: 
78:     public function register(): void
79:     {
80:         $this->app->register(RouteServiceProvider::class);
81: 
82:         $this->app->singleton(WorkflowDefinitionNormalizer::class);
83:         $this->app->singleton(ExpressionLanguageValidator::class);
84:         $this->app->singleton(SyntaxVerificationRule::class);
85:         $this->app->singleton(GraphControlFlowVerificationRule::class);
86:         $this->app->singleton(ExpressionVerificationRule::class);
87:         $this->app->singleton(ContextualVerificationRule::class);
88:         $this->app->singleton(WorkflowVerificationService::class);
89:         $this->app->singleton(WorkflowManagementService::class);
90: 
91:         // Execution engine — M0: foundations.
92:         $this->app->singleton(ExpressionEvaluator::class);
93:         $this->app->singleton(TemplateInterpolator::class);
94:         $this->app->singleton(ExecutionPlanCompiler::class);
95:         $this->app->singleton(NodeExecutorRegistry::class);
96: 
97:         // Execution engine — M1: reliability layer + runtime.
98:         $this->app->singleton(FailureClassifier::class);
99:         $this->app->singleton(RetryPolicy::class);
100:         $this->app->singleton(WorkflowRuntime::class);
101:         $this->app->singleton(WorkflowDispatcher::class);
102: 
103:         // Execution engine — M2: admission control.
104:         $this->app->singleton(InstanceAdmissionService::class);
105: 
106:         // AI generator contract — swap NullAiContentGenerator for a real provider when available.
107:         $this->app->bind(AiContentGenerator::class, NullAiContentGenerator::class);
108: 
109:         // Register node executors keyed by node type.
110:         $this->app->afterResolving(NodeExecutorRegistry::class, function (NodeExecutorRegistry $registry): void {
111:             // M1 executors
112:             $registry->register($this->app->make(ManualTriggerExecutor::class));
113:             $registry->register($this->app->make(FormTriggerExecutor::class));
114:             $registry->register($this->app->make(WebhookTriggerExecutor::class));
115:             $registry->register($this->app->make(IfNodeExecutor::class));
116:             $registry->register($this->app->make(SwitchNodeExecutor::class));
117:             $registry->register($this->app->make(TerminationNodeExecutor::class));
118:             $registry->register($this->app->make(SendEmailExecutor::class));
119:             $registry->register($this->app->make(AiGeneratorExecutor::class));
120: 
121:             // M2 executors
122:             $registry->register($this->app->make(ForkNodeExecutor::class));
123:             $registry->register($this->app->make(TaskNodeExecutor::class));
124:             $registry->register($this->app->make(SubWorkflowExecutor::class));
125:             $registry->register($this->app->make(DynamicFlowExecutor::class));
126:             $registry->register($this->app->make(DynamicEntryExecutor::class));
127: 
128:             // MergeNodeExecutor handles merge-and (its canonical type), merge-or, and the unified
129:             // 'merge' type (config.mergeMode selects parallel vs conditional at plan-compile time).
130:             $merge = $this->app->make(MergeNodeExecutor::class);
131:             $registry->register($merge);
132:             $registry->registerAs('merge-or', $merge);
133:             $registry->registerAs('merge', $merge);
134:         });
135:     }
136: }

(End of file - total 136 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ExpressionLanguageValidator.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ExpressionLanguageValidator.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification;
4: 
5: class ExpressionLanguageValidator
6: {
7:     protected array $tokens = [];
8: 
9:     protected int $position = 0;
10: 
11:     protected array $variables = [];
12: 
13:     public function validateBoolean(string $expression): ExpressionValidationResult
14:     {
15:         $expression = trim($expression);
16: 
17:         if ($expression === '') {
18:             return new ExpressionValidationResult(false, false, [], 'Expression cannot be empty.');
19:         }
20: 
21:         try {
22:             $this->tokens = $this->tokenize($expression);
23:             $this->position = 0;
24:             $this->variables = [];
25: 
26:             $type = $this->parseOr();
27: 
28:             if (! $this->atEnd()) {
29:                 return new ExpressionValidationResult(false, false, $this->variables, 'Unexpected token near "'.$this->currentValue().'".');
30:             }
31: 
32:             $isBoolean = $type === 'boolean';
33: 
34:             return new ExpressionValidationResult(
35:                 $isBoolean,
36:                 $isBoolean,
37:                 array_values(array_unique($this->variables)),
38:                 $isBoolean ? null : 'Expression must use a comparison or logical operator (e.g. context.age > 18). A bare variable reference is not a valid condition.'
39:             );
40:         } catch (\RuntimeException $exception) {
41:             return new ExpressionValidationResult(false, false, array_values(array_unique($this->variables)), $exception->getMessage());
42:         }
43:     }
44: 
45:     protected function tokenize(string $expression): array
46:     {
47:         $tokens = [];
48:         $length = strlen($expression);
49:         $offset = 0;
50: 
51:         while ($offset < $length) {
52:             if (preg_match('/\G\s+/A', $expression, $match, 0, $offset)) {
53:                 $offset += strlen($match[0]);
54: 
55:                 continue;
56:             }
57: 
58:             if (preg_match('/\G(&&|\|\||==|!=|>=|<=|>|<|!|\(|\))/A', $expression, $match, 0, $offset)) {
59:                 $tokens[] = ['type' => 'operator', 'value' => $match[1]];
60:                 $offset += strlen($match[1]);
61: 
62:                 continue;
63:             }
64: 
65:             if (preg_match('/\G"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/A', $expression, $match, 0, $offset)) {
66:                 $tokens[] = ['type' => 'string', 'value' => $match[0]];
67:                 $offset += strlen($match[0]);
68: 
69:                 continue;
70:             }
71: 
72:             if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?/A', $expression, $match, 0, $offset)) {
73:                 $tokens[] = ['type' => 'number', 'value' => $match[0]];
74:                 $offset += strlen($match[0]);
75: 
76:                 continue;
77:             }
78: 
79:             if (preg_match('/\G[A-Za-z_][A-Za-z0-9_.]*/A', $expression, $match, 0, $offset)) {
80:                 $value = $match[0];
81:                 $tokens[] = [
82:                     'type' => in_array(strtolower($value), ['true', 'false', 'null'], true) ? 'literal' : 'identifier',
83:                     'value' => $value,
84:                 ];
85:                 $offset += strlen($value);
86: 
87:                 continue;
88:             }
89: 
90:             throw new \RuntimeException('Invalid expression token near "'.substr($expression, $offset, 12).'".');
91:         }
92: 
93:         return $tokens;
94:     }
95: 
96:     protected function parseOr(): string
97:     {
98:         $type = $this->parseAnd();
99: 
100:         while ($this->consumeOperator('||')) {
101:             $right = $this->parseAnd();
102:             $this->assertBooleanLike($type, 'Left side of || must be boolean.');
103:             $this->assertBooleanLike($right, 'Right side of || must be boolean.');
104:             $type = 'boolean';
105:         }
106: 
107:         return $type;
108:     }
109: 
110:     protected function parseAnd(): string
111:     {
112:         $type = $this->parseComparison();
113: 
114:         while ($this->consumeOperator('&&')) {
115:             $right = $this->parseComparison();
116:             $this->assertBooleanLike($type, 'Left side of && must be boolean.');
117:             $this->assertBooleanLike($right, 'Right side of && must be boolean.');
118:             $type = 'boolean';
119:         }
120: 
121:         return $type;
122:     }
123: 
124:     protected function parseComparison(): string
125:     {
126:         $type = $this->parseUnary();
127: 
128:         if ($this->consumeAnyOperator(['==', '!=', '>=', '<=', '>', '<'])) {
129:             $this->parseUnary();
130: 
131:             return 'boolean';
132:         }
133: 
134:         return $type;
135:     }
136: 
137:     protected function parseUnary(): string
138:     {
139:         if ($this->consumeOperator('!')) {
140:             $type = $this->parseUnary();
141:             $this->assertBooleanLike($type, 'Negated expression must be boolean.');
142: 
143:             return 'boolean';
144:         }
145: 
146:         return $this->parsePrimary();
147:     }
148: 
149:     protected function parsePrimary(): string
150:     {
151:         if ($this->consumeOperator('(')) {
152:             $type = $this->parseOr();
153: 
154:             if (! $this->consumeOperator(')')) {
155:                 throw new \RuntimeException('Missing closing parenthesis.');
156:             }
157: 
158:             return $type;
159:         }
160: 
161:         $token = $this->next();
162: 
163:         if ($token === null) {
164:             throw new \RuntimeException('Unexpected end of expression.');
165:         }
166: 
167:         if ($token['type'] === 'identifier') {
168:             $this->variables[] = $token['value'];
169: 
170:             return 'variable';
171:         }
172: 
173:         if ($token['type'] === 'number') {
174:             return 'number';
175:         }
176: 
177:         if ($token['type'] === 'string') {
178:             return 'string';
179:         }
180: 
181:         if ($token['type'] === 'literal') {
182:             return strtolower($token['value']) === 'null' ? 'null' : 'boolean';
183:         }
184: 
185:         throw new \RuntimeException('Unexpected token "'.$token['value'].'".');
186:     }
187: 
188:     protected function assertBooleanLike(string $type, string $message): void
189:     {
190:         if (! in_array($type, ['boolean', 'variable'], true)) {
191:             throw new \RuntimeException($message);
192:         }
193:     }
194: 
195:     protected function consumeOperator(string $operator): bool
196:     {
197:         $token = $this->tokens[$this->position] ?? null;
198: 
199:         if ($token !== null && $token['type'] === 'operator' && $token['value'] === $operator) {
200:             $this->position++;
201: 
202:             return true;
203:         }
204: 
205:         return false;
206:     }
207: 
208:     protected function consumeAnyOperator(array $operators): ?string
209:     {
210:         $token = $this->tokens[$this->position] ?? null;
211: 
212:         if ($token !== null && $token['type'] === 'operator' && in_array($token['value'], $operators, true)) {
213:             $this->position++;
214: 
215:             return $token['value'];
216:         }
217: 
218:         return null;
219:     }
220: 
221:     protected function next(): ?array
222:     {
223:         $token = $this->tokens[$this->position] ?? null;
224: 
225:         if ($token !== null) {
226:             $this->position++;
227:         }
228: 
229:         return $token;
230:     }
231: 
232:     protected function atEnd(): bool
233:     {
234:         return $this->position >= count($this->tokens);
235:     }
236: 
237:     protected function currentValue(): string
238:     {
239:         return (string) ($this->tokens[$this->position]['value'] ?? 'end of expression');
240:     }
241: }

(End of file - total 241 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Expression/ExpressionEvaluator.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Expression/ExpressionEvaluator.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Execution\Expression;
4: 
5: use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
6: use RuntimeException;
7: 
8: /**
9:  * Runtime evaluator for the boolean/comparison expression language used by `if-node` and conditional edges.
10:  *
11:  * The grammar mirrors {@see ExpressionLanguageValidator} exactly
12:  * (same tokens, same precedence) so that any expression the validator accepts at authoring time is evaluable
13:  * here at runtime — "validated ⇒ evaluable". It is intentionally sandboxed: only the operators
14:  * `&& || == != >= <= > < !`, parentheses, and string/number/true/false/null literals. No function calls,
15:  * no PHP eval — this is a user-authored-input boundary.
16:  *
17:  * Identifiers (e.g. `context.age`, `nodeKey.output.field`) resolve as dot-paths against the supplied data.
18:  * A missing identifier resolves to `null` rather than throwing, so a sparse context never crashes a run.
19:  */
20: class ExpressionEvaluator
21: {
22:     /** @var array<int, array{type: string, value: string}> */
23:     protected array $tokens = [];
24: 
25:     protected int $position = 0;
26: 
27:     /** @var array<string, mixed> */
28:     protected array $data = [];
29: 
30:     /**
31:      * Evaluate an expression to its raw value.
32:      *
33:      * @param  array<string, mixed>  $data  resolution scope (e.g. ['context' => [...], 'trigger' => [...]])
34:      */
35:     public function evaluate(string $expression, array $data = []): mixed
36:     {
37:         $expression = trim($expression);
38: 
39:         if ($expression === '') {
40:             throw new RuntimeException('Cannot evaluate an empty expression.');
41:         }
42: 
43:         $this->tokens = $this->tokenize($expression);
44:         $this->position = 0;
45:         $this->data = $data;
46: 
47:         $value = $this->parseOr();
48: 
49:         if (! $this->atEnd()) {
50:             throw new RuntimeException('Unexpected token near "'.$this->currentValue().'".');
51:         }
52: 
53:         return $value;
54:     }
55: 
56:     /**
57:      * Evaluate an expression and coerce the result to a boolean (truthiness).
58:      *
59:      * @param  array<string, mixed>  $data
60:      */
61:     public function evaluateBoolean(string $expression, array $data = []): bool
62:     {
63:         return $this->toBool($this->evaluate($expression, $data));
64:     }
65: 
66:     /**
67:      * @return array<int, array{type: string, value: string}>
68:      */
69:     protected function tokenize(string $expression): array
70:     {
71:         $tokens = [];
72:         $length = strlen($expression);
73:         $offset = 0;
74: 
75:         while ($offset < $length) {
76:             if (preg_match('/\G\s+/A', $expression, $match, 0, $offset)) {
77:                 $offset += strlen($match[0]);
78: 
79:                 continue;
80:             }
81: 
82:             if (preg_match('/\G(&&|\|\||==|!=|>=|<=|>|<|!|\(|\))/A', $expression, $match, 0, $offset)) {
83:                 $tokens[] = ['type' => 'operator', 'value' => $match[1]];
84:                 $offset += strlen($match[1]);
85: 
86:                 continue;
87:             }
88: 
89:             if (preg_match('/\G"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/A', $expression, $match, 0, $offset)) {
90:                 $tokens[] = ['type' => 'string', 'value' => $match[0]];
91:                 $offset += strlen($match[0]);
92: 
93:                 continue;
94:             }
95: 
96:             if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?/A', $expression, $match, 0, $offset)) {
97:                 $tokens[] = ['type' => 'number', 'value' => $match[0]];
98:                 $offset += strlen($match[0]);
99: 
100:                 continue;
101:             }
102: 
103:             if (preg_match('/\G[A-Za-z_][A-Za-z0-9_.]*/A', $expression, $match, 0, $offset)) {
104:                 $value = $match[0];
105:                 $tokens[] = [
106:                     'type' => in_array(strtolower($value), ['true', 'false', 'null'], true) ? 'literal' : 'identifier',
107:                     'value' => $value,
108:                 ];
109:                 $offset += strlen($value);
110: 
111:                 continue;
112:             }
113: 
114:             throw new RuntimeException('Invalid expression token near "'.substr($expression, $offset, 12).'".');
115:         }
116: 
117:         return $tokens;
118:     }
119: 
120:     protected function parseOr(): mixed
121:     {
122:         $value = $this->parseAnd();
123: 
124:         while ($this->consumeOperator('||')) {
125:             if ($this->toBool($value)) {
126:                 $this->skipAnd();          // short-circuit: still consume the RHS tokens
127:                 $value = true;
128: 
129:                 continue;
130:             }
131: 
132:             $value = $this->toBool($this->parseAnd());
133:         }
134: 
135:         return $value;
136:     }
137: 
138:     protected function parseAnd(): mixed
139:     {
140:         $value = $this->parseComparison();
141: 
142:         while ($this->consumeOperator('&&')) {
143:             if (! $this->toBool($value)) {
144:                 $this->skipComparison();   // short-circuit: still consume the RHS tokens
145:                 $value = false;
146: 
147:                 continue;
148:             }
149: 
150:             $value = $this->toBool($this->parseComparison());
151:         }
152: 
153:         return $value;
154:     }
155: 
156:     protected function parseComparison(): mixed
157:     {
158:         $left = $this->parseUnary();
159: 
160:         $operator = $this->consumeAnyOperator(['==', '!=', '>=', '<=', '>', '<']);
161: 
162:         if ($operator !== null) {
163:             $right = $this->parseUnary();
164: 
165:             return $this->compare($operator, $left, $right);
166:         }
167: 
168:         return $left;
169:     }
170: 
171:     protected function parseUnary(): mixed
172:     {
173:         if ($this->consumeOperator('!')) {
174:             return ! $this->toBool($this->parseUnary());
175:         }
176: 
177:         return $this->parsePrimary();
178:     }
179: 
180:     protected function parsePrimary(): mixed
181:     {
182:         if ($this->consumeOperator('(')) {
183:             $value = $this->parseOr();
184: 
185:             if (! $this->consumeOperator(')')) {
186:                 throw new RuntimeException('Missing closing parenthesis.');
187:             }
188: 
189:             return $value;
190:         }
191: 
192:         $token = $this->next();
193: 
194:         if ($token === null) {
195:             throw new RuntimeException('Unexpected end of expression.');
196:         }
197: 
198:         return match ($token['type']) {
199:             'identifier' => data_get($this->data, $token['value']),
200:             'number' => $this->parseNumber($token['value']),
201:             'string' => $this->parseString($token['value']),
202:             'literal' => $this->parseLiteral($token['value']),
203:             default => throw new RuntimeException('Unexpected token "'.$token['value'].'".'),
204:         };
205:     }
206: 
207:     protected function compare(string $operator, mixed $left, mixed $right): bool
208:     {
209:         return match ($operator) {
210:             '==' => $this->looseEquals($left, $right),
211:             '!=' => ! $this->looseEquals($left, $right),
212:             '>', '<', '>=', '<=' => $this->orderedCompare($operator, $left, $right),
213:             default => throw new RuntimeException('Unknown operator "'.$operator.'".'),
214:         };
215:     }
216: 
217:     protected function looseEquals(mixed $a, mixed $b): bool
218:     {
219:         if ($a === null || $b === null) {
220:             return $a === $b;
221:         }
222: 
223:         if (is_bool($a) || is_bool($b)) {
224:             return $this->toBool($a) === $this->toBool($b);
225:         }
226: 
227:         if (is_numeric($a) && is_numeric($b)) {
228:             return (float) $a === (float) $b;
229:         }
230: 
231:         return (string) $a === (string) $b;
232:     }
233: 
234:     protected function orderedCompare(string $operator, mixed $a, mixed $b): bool
235:     {
236:         if ($a === null || $b === null) {
237:             return false;
238:         }
239: 
240:         $ordering = (is_numeric($a) && is_numeric($b))
241:             ? (float) $a <=> (float) $b
242:             : strcmp((string) $a, (string) $b);
243: 
244:         return match ($operator) {
245:             '>' => $ordering > 0,
246:             '<' => $ordering < 0,
247:             '>=' => $ordering >= 0,
248:             '<=' => $ordering <= 0,
249:             default => false,
250:         };
251:     }
252: 
253:     protected function parseNumber(string $value): int|float
254:     {
255:         return str_contains($value, '.') ? (float) $value : (int) $value;
256:     }
257: 
258:     protected function parseString(string $value): string
259:     {
260:         return stripcslashes(substr($value, 1, -1));
261:     }
262: 
263:     protected function parseLiteral(string $value): ?bool
264:     {
265:         return match (strtolower($value)) {
266:             'true' => true,
267:             'false' => false,
268:             default => null,
269:         };
270:     }
271: 
272:     protected function toBool(mixed $value): bool
273:     {
274:         if (is_bool($value)) {
275:             return $value;
276:         }
277: 
278:         if ($value === null) {
279:             return false;
280:         }
281: 
282:         if (is_numeric($value)) {
283:             return (float) $value !== 0.0;
284:         }
285: 
286:         if (is_string($value)) {
287:             return $value !== '';
288:         }
289: 
290:         if (is_array($value)) {
291:             return $value !== [];
292:         }
293: 
294:         return (bool) $value;
295:     }
296: 
297:     protected function consumeOperator(string $operator): bool
298:     {
299:         $token = $this->tokens[$this->position] ?? null;
300: 
301:         if ($token !== null && $token['type'] === 'operator' && $token['value'] === $operator) {
302:             $this->position++;
303: 
304:             return true;
305:         }
306: 
307:         return false;
308:     }
309: 
310:     /**
311:      * @param  array<int, string>  $operators
312:      */
313:     protected function consumeAnyOperator(array $operators): ?string
314:     {
315:         $token = $this->tokens[$this->position] ?? null;
316: 
317:         if ($token !== null && $token['type'] === 'operator' && in_array($token['value'], $operators, true)) {
318:             $this->position++;
319: 
320:             return $token['value'];
321:         }
322: 
323:         return null;
324:     }
325: 
326:     /**
327:      * Consume and discard an `&&`-level subexpression (used for short-circuit evaluation).
328:      */
329:     protected function skipComparison(): void
330:     {
331:         $this->parseComparison();
332:     }
333: 
334:     protected function skipAnd(): void
335:     {
336:         $this->parseAnd();
337:     }
338: 
339:     /**
340:      * @return array{type: string, value: string}|null
341:      */
342:     protected function next(): ?array
343:     {
344:         $token = $this->tokens[$this->position] ?? null;
345: 
346:         if ($token !== null) {
347:             $this->position++;
348:         }
349: 
350:         return $token;
351:     }
352: 
353:     protected function atEnd(): bool
354:     {
355:         return $this->position >= count($this->tokens);
356:     }
357: 
358:     protected function currentValue(): string
359:     {
360:         return (string) ($this->tokens[$this->position]['value'] ?? 'end of expression');
361:     }
362: }

(End of file - total 362 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowManagementService.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowManagementService.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services;
4: 
5: use Illuminate\Auth\Access\AuthorizationException;
6: use Illuminate\Database\Eloquent\Builder;
7: use Illuminate\Database\Eloquent\Collection;
8: use Illuminate\Support\Facades\DB;
9: use Illuminate\Validation\ValidationException;
10: use Modules\Auth\Enums\Role;
11: use Modules\Auth\Models\User;
12: use Modules\Team\Models\Team;
13: use Modules\Workflows\Enums\WorkflowStatus;
14: use Modules\Workflows\Models\Workflow;
15: use Symfony\Component\HttpKernel\Exception\HttpException;
16: 
17: class WorkflowManagementService
18: {
19:     public function __construct(
20:         protected WorkflowVerificationService $verificationService,
21:         protected WorkflowAuthorizationService $authorizationService,
22:         protected WorkflowTemplateService $templateService,
23:     ) {}
24: 
25:     public function listVisibleWorkflows(User $actor, array $filters = []): Collection
26:     {
27:         $query = Workflow::query()->visibleTo($actor);
28: 
29:         return $query
30:             ->when(
31:                 empty($filters['include_deleted']),
32:                 function (Builder $query): void {
33:                     $query->where(
34:                         'status',
35:                         '!=',
36:                         WorkflowStatus::Deleted->value
37:                     );
38:                 }
39:             )
40:             ->when(
41:                 $filters['q'] ?? null,
42:                 function (Builder $query, string $search): void {
43:                     $query->where(function (Builder $query) use ($search): void {
44:                         $query->where('name', 'like', "%{$search}%")
45:                             ->orWhere('description', 'like', "%{$search}%");
46:                     });
47:                 }
48:             )
49:             ->when(
50:                 $filters['name'] ?? null,
51:                 fn (Builder $query, string $name) => $query->where('name', 'like', "%{$name}%")
52:             )
53:             ->when(
54:                 $filters['description'] ?? null,
55:                 fn (Builder $query, string $description) => $query->where('description', 'like', "%{$description}%")
56:             )
57:             ->when(
58:                 $filters['team_id'] ?? null,
59:                 fn (Builder $query, $teamId) => $query->where('team_id', (int) $teamId)
60:             )
61:             ->when(
62:                 $filters['status'] ?? null,
63:                 fn (Builder $query, string $status) => $query->where('status', $status)
64:             )
65:             ->when(
66:                 $filters['created_from'] ?? null,
67:                 fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date)
68:             )
69:             ->when(
70:                 $filters['created_to'] ?? null,
71:                 fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date)
72:             )
73:             ->with([
74:                 'team:id,name',
75:                 'createdBy:id,first_name,last_name,name,email',
76:             ])
77:             ->latest('updated_at')
78:             ->get();
79:     }
80: 
81:     public function createWorkflow(User $actor, array $data): Workflow
82:     {
83:         if (! in_array($actor->role, [Role::Manager, Role::BusinessOwner], true)) {
84:             throw new AuthorizationException('Only managers and business owners can create workflows.');
85:         }
86: 
87:         if ($actor->role === Role::Manager && $team = $actor->getManagedTeam() === null) {
88:             throw new AuthorizationException('Manager must belong to a team before managing workflows.');
89:         }
90: 
91:         if (empty($data['team_id'])) {
92:             throw ValidationException::withMessages([
93:                 'team_id' => 'The team id is required to assign workflow to cause you are a Business Owners.',
94:             ]);
95:         }
96: 
97:         $team = Team::query()
98:             ->where('tenant_id', (int) $actor->tenant_id)
99:             ->where('id', (int) $data['team_id'])
100:             ->first();
101: 
102:         if ($team === null) {
103:             throw ValidationException::withMessages([
104:                 'team_id' => 'The selected team is invalid or does not belong to your tenant.',
105:             ]);
106:         }
107: 
108:         $method = $data['method'];
109: 
110:         return DB::transaction(function () use ($actor, $team, $method, $data): Workflow {
111:             $template = null;
112:             $definition = [
113:                 'trigger' => null,
114:                 'nodes' => [],
115:                 'edges' => [],
116:                 'settings' => [],
117:             ];
118: 
119:             if ($method === 'template') {
120:                 $template = $this->templateService->resolveTemplate($actor, (int) $data['template_id']);
121:                 $definition = $template->definition;
122:                 $template->increment('usage_count');
123:             }
124: 
125:             if ($method === 'ai_confirmed') {
126:                 $definition = $data['definition'];
127:             }
128: 
129:             $workflow = Workflow::query()->create([
130:                 'tenant_id' => (int) $actor->tenant_id,
131:                 'team_id' => (int) $team->id,
132:                 'template_id' => $template?->id,
133:                 'created_by_id' => (int) $actor->id,
134:                 'name' => trim((string) $data['name']),
135:                 'description' => isset($data['description']) ? trim((string) $data['description']) : null,
136:                 'status' => WorkflowStatus::Disabled,
137:                 'draft_definition' => $definition,
138:                 'draft_revision' => 1,
139:                 'current_version_number' => 0,
140:                 'total_runs' => 0,
141:                 'active_instances' => 0,
142:             ]);
143: 
144:             return $workflow->refresh()->load(['team:id,name', 'createdBy:id,first_name,last_name,name,email']);
145:         });
146:     }
147: 
148:     public function getVisibleWorkflow(User $actor, Workflow $workflow): Workflow
149:     {
150:         $this->authorizationService->assertCanView($actor, $workflow);
151: 
152:         return $workflow->load([
153:             'team:id,name',
154:             'createdBy:id,first_name,last_name,name,email',
155:             'currentVersion',
156:             'template:id,name',
157:         ]);
158:     }
159: 
160:     public function updateDraft(User $actor, Workflow $workflow, array $data): array
161:     {
162:         $this->authorizationService->assertCanManage($actor, $workflow);
163:         $this->assertNotDeleted($workflow);
164: 
165:         // Check if the draft revision matches the expected revision to prevent overwriting changes
166:         if ((int) $workflow->draft_revision !== (int) $data['expected_draft_revision']) {
167:             throw new HttpException(409, 'Workflow draft has been modified. Refresh and retry.');
168:         }
169: 
170:         $validation = $this->verificationService->verify($data['definition'], $workflow, $actor)->toArray();
171: 
172:         if ((bool) ($data['validate_only'] ?? false)) {
173:             return [
174:                 'workflow' => $workflow,
175:                 'saved' => false,
176:                 'validation' => $validation,
177:             ];
178:         }
179: 
180:         $workflow->forceFill([
181:             'draft_definition' => $data['definition'],
182:             'draft_revision' => (int) $workflow->draft_revision + 1,
183:         ])->save();
184: 
185:         return [
186:             'workflow' => $workflow->refresh(),
187:             'saved' => true,
188:             'validation' => $validation,
189:         ];
190:     }
191: 
192:     public function updateStatus(User $actor, Workflow $workflow, string $status): Workflow
193:     {
194:         $this->authorizationService->assertCanManage($actor, $workflow);
195:         $this->assertNotDeleted($workflow);
196: 
197:         if ($status === WorkflowStatus::Active->value && $workflow->current_version_id === null) {
198:             throw ValidationException::withMessages([
199:                 'status' => 'Workflow must be published before it can be activated.',
200:             ]);
201:         }
202: 
203:         $workflow->forceFill(['status' => $status])->save();
204: 
205:         return $workflow->refresh();
206:     }
207: 
208:     public function softDelete(User $actor, Workflow $workflow): Workflow
209:     {
210:         $this->authorizationService->assertCanManage($actor, $workflow);
211: 
212:         $workflow->forceFill([
213:             'status' => WorkflowStatus::Deleted,
214:             'deleted_at' => now(),
215:         ])->save();
216: 
217:         return $workflow->refresh();
218:     }
219: 
220:     public function purge(User $actor, Workflow $workflow): void
221:     {
222:         $this->authorizationService->assertBusinessOwner($actor);
223:         $this->authorizationService->assertSameTenant($actor, $workflow);
224: 
225:         if ($workflow->status !== WorkflowStatus::Deleted) {
226:             throw ValidationException::withMessages([
227:                 'workflow' => 'Workflow must be deleted before it can be purged.',
228:             ]);
229:         }
230: 
231:         if ((int) $workflow->active_instances > 0) {
232:             throw ValidationException::withMessages([
233:                 'workflow' => 'Workflow cannot be purged while active instances exist.',
234:             ]);
235:         }
236: 
237:         DB::transaction(function () use ($workflow): void {
238:             $workflow->instances()->delete();
239:             $workflow->versions()->delete();
240:             $workflow->delete();
241:         });
242:     }
243: 
244:     protected function assertNotDeleted(Workflow $workflow): void
245:     {
246:         if ($workflow->status === WorkflowStatus::Deleted) {
247:             throw ValidationException::withMessages([
248:                 'workflow' => 'Deleted workflows cannot be modified.',
249:             ]);
250:         }
251:     }
252: }

(End of file - total 252 lines)
</content>
```

---

## Assistant (Build · Big Pickle · 24.0s)

_Thinking:_

Hmm, the WorkflowManagementService I read is 252 lines, not 777 lines as mentioned in the review. Let me check if there's more to this file or if the review was referring to something else. Let me look at what else might be in the service or if there are additional methods I'm missing. Let me also check the other services that are mentioned.

Wait, I notice the review mentioned777 lines but the file I read is only 252 lines. The review might have been combining multiple services or there might be additional content. Let me check if the file was truncated or if there are other related services.

Actually, looking more carefully, the 252-line file doesn't have the publish, trigger, or serializeWorkflow methods mentioned in the review. Let me search for where those methods are.

**Tool: grep**

**Input:**
```json
{
  "pattern": "function (publish|triggerWebhook|triggerManual|triggerForm|serializeWorkflow)",
  "path": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services",
  "include": "*.php"
}
```

**Output:**
```
Found 4 matches
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVersioningService.php:
  Line 20:     public function publish(User $actor, Workflow $workflow, array $data): WorkflowVersion


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowTriggeringService.php:
  Line 21:     public function triggerWebhook(User $actor, Workflow $workflow, array $payload = []): WorkflowInstance

  Line 32:     public function triggerManual(User $actor, Workflow $workflow, array $payload = []): WorkflowInstance

  Line 43:     public function triggerForm(User $actor, Workflow $workflow, array $formData = []): WorkflowInstance

```

**Tool: grep**

**Input:**
```json
{
  "pattern": "class WorkflowManagementService",
  "path": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows",
  "include": "*.php"
}
```

**Output:**
```
Found 1 matches
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowManagementService.php:
  Line 17: class WorkflowManagementService

```

**Tool: glob**

**Input:**
```json
{
  "pattern": "Modules/Workflows/app/Services/Workflow*Service.php"
}
```

**Output:**
```
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowAuthorizationService.php
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowTemplateService.php
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVerificationService.php
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowTriggeringService.php
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowManagementService.php
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVersioningService.php
```

---

## Assistant (Build · Big Pickle · 15.1s)

_Thinking:_

So the service is already split! There's already:
- `WorkflowManagementService` (252 lines) - CRUD operations
- `WorkflowVersioningService` - publish/versioning
- `WorkflowTriggeringService` - trigger operations
- `WorkflowAuthorizationService` - authorization
- `WorkflowTemplateService` - templates

The review was incorrect about this being a 777-line god class. It's already been split. Let me read the other services to confirm.

Actually wait, the review mentioned `serializeWorkflow()` and some other methods. Let me check if those are in a different file or if the review was simply wrong about the line count.

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVersioningService.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVersioningService.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services;
4: 
5: use App\Services\BaseService;
6: use Illuminate\Support\Facades\DB;
7: use Illuminate\Validation\ValidationException;
8: use Modules\Auth\Models\User;
9: use Modules\Workflows\Enums\WorkflowStatus;
10: use Modules\Workflows\Models\Workflow;
11: use Modules\Workflows\Models\WorkflowVersion;
12: 
13: class WorkflowVersioningService extends BaseService
14: {
15:     public function __construct(
16:         protected WorkflowVerificationService $verificationService,
17:         protected WorkflowAuthorizationService $authorizationService,
18:     ) {}
19: 
20:     public function publish(User $actor, Workflow $workflow, array $data): WorkflowVersion
21:     {
22:         $this->authorizationService->assertCanManage($actor, $workflow);
23:         $this->assertNotDeleted($workflow);
24: 
25:         $validation = $this->verificationService->verify($workflow->draft_definition, $workflow, $actor)->toArray();
26: 
27:         if (! $validation['is_publishable']) {
28:             throw ValidationException::withMessages([
29:                 'definition' => $validation['errors'],
30:             ]);
31:         }
32: 
33:         $workflow->loadMissing('currentVersion');
34:         $changeType = $this->detectPublishChangeType($workflow);
35: 
36:         if ($changeType === 'none') {
37:             $workflow->update(['status' => WorkflowStatus::Active]);
38: 
39:             return $workflow->currentVersion->load('publishedBy:id,first_name,last_name,name,email');
40:         }
41: 
42:         return DB::transaction(function () use ($actor, $workflow, $changeType): WorkflowVersion {
43:             $nextVersionNumber = ((int) $workflow->versions()->max('version_number')) + 1;
44:             $versionLabel = $this->nextVersionLabel($workflow->current_version_label, $changeType);
45: 
46:             if ($workflow->versions()->where('version_label', $versionLabel)->exists()) {
47:                 throw ValidationException::withMessages([
48:                     'version_label' => 'Version label already exists for this workflow.',
49:                 ]);
50:             }
51: 
52:             $version = WorkflowVersion::query()->create([
53:                 'workflow_id' => (int) $workflow->id,
54:                 'tenant_id' => (int) $workflow->tenant_id,
55:                 'version_number' => $nextVersionNumber,
56:                 'version_label' => $versionLabel,
57:                 'definition' => $workflow->draft_definition,
58:                 'published_by_id' => (int) $actor->id,
59:                 'published_at' => now(),
60:             ]);
61: 
62:             $workflow->forceFill([
63:                 'current_version_id' => (int) $version->id,
64:                 'current_version_number' => $nextVersionNumber,
65:                 'current_version_label' => $versionLabel,
66:                 'status' => WorkflowStatus::Active,
67:             ])->save();
68: 
69:             return $version->load('publishedBy:id,first_name,last_name,name,email');
70:         });
71:     }
72: 
73:     protected function detectPublishChangeType(Workflow $workflow): string
74:     {
75:         $currentVersion = $workflow->currentVersion;
76: 
77:         if ($currentVersion === null) {
78:             return 'structural';
79:         }
80: 
81:         $currentDefinition = is_array($currentVersion->definition ?? null) ? $currentVersion->definition : [];
82:         $draftDefinition = is_array($workflow->draft_definition ?? null) ? $workflow->draft_definition : [];
83: 
84:         if (
85:             $this->fingerprint($this->structuralDefinition($currentDefinition))
86:             !== $this->fingerprint($this->structuralDefinition($draftDefinition))
87:         ) {
88:             return 'structural';
89:         }
90: 
91:         if (
92:             $this->fingerprint($this->configDefinition($currentDefinition))
93:             !== $this->fingerprint($this->configDefinition($draftDefinition))
94:         ) {
95:             return 'config';
96:         }
97: 
98:         return 'none';
99:     }
100: 
101:     protected function nextVersionLabel(?string $currentLabel, string $changeType): string
102:     {
103:         if ($currentLabel === null || ! preg_match('/^v(\d+)\.(\d+)\.(\d+)$/', $currentLabel, $matches)) {
104:             return 'v1.0.0';
105:         }
106: 
107:         $major = (int) $matches[1];
108:         $minor = (int) $matches[2];
109:         $patch = (int) $matches[3];
110: 
111:         return match ($changeType) {
112:             'structural' => sprintf('v%d.%d.%d', $major + 1, $minor + 1, $patch),
113:             'config' => sprintf('v%d.%d.%d', $major + 1, $minor, $patch + 1),
114:             default => sprintf('v%d.%d.%d', $major + 1, $minor, $patch),
115:         };
116:     }
117: 
118:     public function listVersions(User $actor, Workflow $workflow)
119:     {
120:         $this->authorizationService->assertCanView($actor, $workflow);
121: 
122:         return $workflow->versions()
123:             ->with('publishedBy:id,first_name,last_name,name,email')
124:             ->orderByDesc('version_number')
125:             ->get();
126:     }
127: 
128:     protected function structuralDefinition(array $definition): array
129:     {
130:         return [
131:             'trigger' => $this->structuralTrigger($definition['trigger'] ?? null),
132:             'nodes' => array_values(array_map(
133:                 fn (mixed $node): array => $this->structuralNode($node),
134:                 is_array($definition['nodes'] ?? null) ? $definition['nodes'] : []
135:             )),
136:             'edges' => array_values(array_map(
137:                 fn (mixed $edge): array => $this->structuralEdge($edge),
138:                 is_array($definition['edges'] ?? null) ? $definition['edges'] : []
139:             )),
140:         ];
141:     }
142: 
143:     protected function configDefinition(array $definition): array
144:     {
145:         return [
146:             'trigger_config' => $this->configTrigger($definition['trigger'] ?? null),
147:             'variables' => is_array($definition['variables'] ?? null) ? array_values($definition['variables']) : [],
148:             'nodes' => array_values(array_map(
149:                 fn (mixed $node): array => $this->configNode($node),
150:                 is_array($definition['nodes'] ?? null) ? $definition['nodes'] : []
151:             )),
152:             'settings' => is_array($definition['settings'] ?? null) ? $definition['settings'] : [],
153:         ];
154:     }
155: 
156:     protected function structuralTrigger(mixed $trigger): array
157:     {
158:         if (! is_array($trigger)) {
159:             return ['type' => null];
160:         }
161: 
162:         return ['type' => isset($trigger['type']) ? (string) $trigger['type'] : null];
163:     }
164: 
165:     protected function configTrigger(mixed $trigger): array
166:     {
167:         if (! is_array($trigger)) {
168:             return [];
169:         }
170: 
171:         return is_array($trigger['config'] ?? null) ? $trigger['config'] : [];
172:     }
173: 
174:     protected function structuralNode(mixed $node): array
175:     {
176:         if (! is_array($node)) {
177:             return [
178:                 'id' => null,
179:                 'type' => null,
180:                 'label' => null,
181:                 'name' => null,
182:                 'is_entry_point' => false,
183:                 'is_terminal' => false,
184:             ];
185:         }
186: 
187:         return [
188:             'id' => isset($node['id']) ? (string) $node['id'] : null,
189:             'type' => isset($node['type']) ? (string) $node['type'] : null,
190:             'label' => isset($node['label']) ? (string) $node['label'] : null,
191:             'name' => isset($node['name']) ? (string) $node['name'] : null,
192:             'is_entry_point' => (bool) ($node['is_entry_point'] ?? false),
193:             'is_terminal' => (bool) ($node['is_terminal'] ?? false),
194:         ];
195:     }
196: 
197:     protected function configNode(mixed $node): array
198:     {
199:         if (! is_array($node)) {
200:             return ['config' => []];
201:         }
202: 
203:         return [
204:             'id' => isset($node['id']) ? (string) $node['id'] : null,
205:             'config' => is_array($node['config'] ?? null) ? $node['config'] : [],
206:         ];
207:     }
208: 
209:     protected function structuralEdge(mixed $edge): array
210:     {
211:         if (! is_array($edge)) {
212:             return [
213:                 'id' => null,
214:                 'source_node_key' => null,
215:                 'target_node_key' => null,
216:                 'condition_expression' => null,
217:                 'is_default_branch' => false,
218:                 'parallel_strategy' => null,
219:                 'join_node_key' => null,
220:                 'sort_order' => 0,
221:             ];
222:         }
223: 
224:         return [
225:             'id' => isset($edge['id']) ? (string) $edge['id'] : null,
226:             'source_node_key' => isset($edge['source_node_key']) ? (string) $edge['source_node_key'] : null,
227:             'target_node_key' => isset($edge['target_node_key']) ? (string) $edge['target_node_key'] : null,
228:             'condition_expression' => isset($edge['condition_expression']) ? (string) $edge['condition_expression'] : null,
229:             'is_default_branch' => (bool) ($edge['is_default_branch'] ?? false),
230:             'parallel_strategy' => isset($edge['parallel_strategy']) ? (string) $edge['parallel_strategy'] : null,
231:             'join_node_key' => isset($edge['join_node_key']) ? (string) $edge['join_node_key'] : null,
232:             'sort_order' => isset($edge['sort_order']) ? (int) $edge['sort_order'] : 0,
233:         ];
234:     }
235: 
236:     protected function fingerprint(mixed $value): string
237:     {
238:         return md5(json_encode($this->sortRecursive($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
239:     }
240: 
241:     protected function sortRecursive(mixed $value): mixed
242:     {
243:         if (! is_array($value)) {
244:             return $value;
245:         }
246: 
247:         foreach ($value as $key => $item) {
248:             $value[$key] = $this->sortRecursive($item);
249:         }
250: 
251:         if (! array_is_list($value)) {
252:             ksort($value);
253:         }
254: 
255:         return $value;
256:     }
257: }

(End of file - total 257 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowTriggeringService.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowTriggeringService.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services;
4: 
5: use App\Services\BaseService;
6: use Modules\Auth\Models\User;
7: use Modules\Workflows\Enums\TriggerType;
8: use Modules\Workflows\Enums\WorkflowStatus;
9: use Modules\Workflows\Models\Workflow;
10: use Modules\Workflows\Models\WorkflowInstance;
11: use Modules\Workflows\Services\Execution\WorkflowDispatcher;
12: use Symfony\Component\HttpKernel\Exception\HttpException;
13: 
14: class WorkflowTriggeringService extends BaseService
15: {
16:     public function __construct(
17:         protected WorkflowAuthorizationService $authorizationService,
18:         protected WorkflowDispatcher $dispatcher,
19:     ) {}
20: 
21:     public function triggerWebhook(User $actor, Workflow $workflow, array $payload = []): WorkflowInstance
22:     {
23:         $this->authorizationService->assertCanView($actor, $workflow);
24: 
25:         if (in_array($workflow->status, [WorkflowStatus::Disabled, WorkflowStatus::Deleted], true)) {
26:             throw new HttpException(410, 'Workflow is not available for triggering.');
27:         }
28: 
29:         return $this->dispatcher->dispatch($workflow, TriggerType::Webhook, $payload);
30:     }
31: 
32:     public function triggerManual(User $actor, Workflow $workflow, array $payload = []): WorkflowInstance
33:     {
34:         $this->authorizationService->assertCanView($actor, $workflow);
35: 
36:         if (in_array($workflow->status, [WorkflowStatus::Disabled, WorkflowStatus::Deleted], true)) {
37:             throw new HttpException(410, 'Workflow is not available for triggering.');
38:         }
39: 
40:         return $this->dispatcher->dispatch($workflow, TriggerType::Manual, $payload);
41:     }
42: 
43:     public function triggerForm(User $actor, Workflow $workflow, array $formData = []): WorkflowInstance
44:     {
45:         $this->authorizationService->assertCanView($actor, $workflow);
46: 
47:         if (in_array($workflow->status, [WorkflowStatus::Disabled, WorkflowStatus::Deleted], true)) {
48:             throw new HttpException(410, 'Workflow is not available for triggering.');
49:         }
50: 
51:         return $this->dispatcher->dispatch($workflow, TriggerType::Form, $formData);
52:     }
53: }

(End of file - total 53 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowAuthorizationService.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowAuthorizationService.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services;
4: 
5: use App\Services\BaseService;
6: use Illuminate\Auth\Access\AuthorizationException;
7: use Modules\Auth\Enums\Role;
8: use Modules\Auth\Models\User;
9: use Modules\Team\Models\Team;
10: use Modules\Team\Models\TeamMembership;
11: use Modules\Workflows\Models\Workflow;
12: 
13: class WorkflowAuthorizationService extends BaseService
14: {
15:     public function assertCanView(User $actor, Workflow $workflow): void
16:     {
17:         if (! $this->canView($actor, $workflow)) {
18:             throw new AuthorizationException('You are not allowed to view this workflow.');
19:         }
20:     }
21: 
22:     public function canView(User $actor, Workflow $workflow): bool
23:     {
24:         if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
25:             return false;
26:         }
27: 
28:         if ($actor->role === Role::BusinessOwner) {
29:             return true;
30:         }
31: 
32:         if ($actor->role === Role::Manager) {
33:             return $this->canManage($actor, $workflow);
34:         }
35: 
36:         if ($actor->role === Role::Employee) {
37:             return TeamMembership::query()
38:                 ->where('tenant_id', (int) $actor->tenant_id)
39:                 ->where('team_id', (int) $workflow->team_id)
40:                 ->where('user_id', (int) $actor->id)
41:                 ->where('status', 'active')
42:                 ->exists();
43:         }
44: 
45:         return false;
46:     }
47: 
48:     public function assertCanManage(User $actor, Workflow $workflow): void
49:     {
50:         if (! $this->canManage($actor, $workflow)) {
51:             throw new AuthorizationException('You are not allowed to manage this workflow.');
52:         }
53:     }
54: 
55:     public function canManage(User $actor, Workflow $workflow): bool
56:     {
57:         if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
58:             return false;
59:         }
60: 
61:         if ($actor->role === Role::BusinessOwner) {
62:             return true;
63:         }
64: 
65:         if ($actor->role !== Role::Manager) {
66:             return false;
67:         }
68: 
69:         $team = Team::query()
70:             ->where('tenant_id', (int) $actor->tenant_id)
71:             ->where('manager_id', (int) $actor->id)
72:             ->first();
73: 
74:         return $team !== null && (int) $workflow->team_id === (int) $team->id;
75:     }
76: 
77:     public function assertBusinessOwner(User $actor): void
78:     {
79:         if ($actor->role !== Role::BusinessOwner) {
80:             throw new AuthorizationException('Only business owners can perform this action.');
81:         }
82:     }
83: 
84:     public function assertSameTenant(User $actor, Workflow $workflow): void
85:     {
86:         if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
87:             throw new AuthorizationException('You can only manage workflows within your tenant.');
88:         }
89:     }
90: }

(End of file - total 90 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowTemplateService.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowTemplateService.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services;
4: 
5: use App\Services\BaseService;
6: use Illuminate\Database\Eloquent\Builder;
7: use Illuminate\Database\Eloquent\Collection;
8: use Illuminate\Validation\ValidationException;
9: use Modules\Auth\Models\User;
10: use Modules\Workflows\Models\WorkflowTemplate;
11: 
12: class WorkflowTemplateService extends BaseService
13: {
14:     public function listTemplates(User $actor): Collection
15:     {
16:         return WorkflowTemplate::query()
17:             ->where('is_active', true)
18:             ->where(function (Builder $query) use ($actor): void {
19:                 $query->whereNull('tenant_id')
20:                     ->orWhere('tenant_id', (int) $actor->tenant_id);
21:             })
22:             ->orderBy('name')
23:             ->get();
24:     }
25: 
26:     public function resolveTemplate(User $actor, int $templateId): WorkflowTemplate
27:     {
28:         $template = WorkflowTemplate::query()
29:             ->where('id', $templateId)
30:             ->where('is_active', true)
31:             ->where(function (Builder $query) use ($actor): void {
32:                 $query->whereNull('tenant_id')
33:                     ->orWhere('tenant_id', (int) $actor->tenant_id);
34:             })
35:             ->first();
36: 
37:         if ($template === null) {
38:             throw ValidationException::withMessages([
39:                 'template_id' => 'Selected workflow template was not found.',
40:             ]);
41:         }
42: 
43:         return $template;
44:     }
45: }

(End of file - total 45 lines)
</content>
```

---

## Assistant (Build · Big Pickle · 9.9s)

_Thinking:_

Good news - Issue 5 is already done! The `WorkflowManagementService` is already split into:
- `WorkflowManagementService` (252 lines) - CRUD operations
- `WorkflowVersioningService` (257 lines) - publish/versioning
- `WorkflowTriggeringService` (53 lines) - trigger operations
- `WorkflowAuthorizationService` (90 lines) - authorization
- `WorkflowTemplateService` (45 lines) - templates

So I only need to focus on Issues 2 and 3. Let me now read all the node type rule files to understand the full picture for Issue 2, and then read the files that use the expression language for Issue 3.

Issue 5 is already done - `WorkflowManagementService` is already split into `WorkflowManagementService`, `WorkflowVersioningService`, `WorkflowTriggeringService`, `WorkflowAuthorizationService`, and `WorkflowTemplateService`. Let me now focus on Issues 2 and 3.

**Tool: todowrite**

**Input:**
```json
{}
```

**Error:**
```
Tool execution aborted
```

---

## User

no your review is correct, I have refactored some code on my own since you gave me the review, I am just stuck on the reset, please tell me how to implement them

---

## Assistant (Plan · DeepSeek V4 Flash Free · 7.8s)

_Thinking:_

The user is in plan mode - I cannot make any edits. They want me to explain how to implement Issues 2 and 3 (since Issue 5 is already done - they refactored it). Let me provide a clear implementation plan for each.

Let me read the remaining node type rule files and the files that need changes to provide a comprehensive plan.

Got it — you already split `WorkflowManagementService`, so we just need Issues 2 and 3. Let me read the remaining files to give you a precise plan.

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/IfNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/IfNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
7: use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
8: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
9: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
10: 
11: class IfNodeTypeRule implements NodeTypeRule
12: {
13:     use VariableAvailability;
14: 
15:     protected const CODE_PREFIX = 'if_node';
16: 
17:     public function __construct(
18:         protected ExpressionLanguageValidator $expressionValidator,
19:     ) {}
20: 
21:     public function nodeType(): string
22:     {
23:         return 'if-node';
24:     }
25: 
26:     public function verify(
27:         array $node,
28:         int $index,
29:         WorkflowDefinitionGraph $graph,
30:         WorkflowVerificationResult $result,
31:         ?Workflow $workflow = null,
32:     ): void {
33:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
34:         $config = is_array($node['config'] ?? null) ? $node['config'] : [];
35:         $exprPath = "nodes[{$index}].config.conditionExpression";
36: 
37:         $expression = trim((string) ($config['conditionExpression'] ?? ''));
38: 
39:         if ($expression === '') {
40:             $result->addError('if_node.expression_missing', 'If node must have a condition expression.', $exprPath, $nodeId);
41:             $this->verifyOutgoingBranches($nodeId, $graph, $result);
42: 
43:             return;
44:         }
45: 
46:         $validation = $this->expressionValidator->validateBoolean($expression);
47: 
48:         if (! $validation->valid) {
49:             $result->addError('if_node.expression_invalid', 'If node condition expression is invalid: '.$validation->message, $exprPath, $nodeId);
50:             $this->verifyOutgoingBranches($nodeId, $graph, $result);
51: 
52:             return;
53:         }
54: 
55:         $this->verifyVariableNamespaces($validation->variables, $exprPath, $nodeId, $result);
56:         $this->verifyContextVariableAvailability($validation->variables, $nodeId, $exprPath, $graph, $result);
57:         $this->verifyOutgoingBranches($nodeId, $graph, $result);
58:     }
59: 
60:     protected function verifyVariableNamespaces(
61:         array $variables,
62:         string $path,
63:         ?string $nodeId,
64:         WorkflowVerificationResult $result,
65:     ): void {
66:         foreach ($variables as $variable) {
67:             $this->validateVariableNamespace($variable, self::CODE_PREFIX, $path, $nodeId, $result);
68:         }
69:     }
70: 
71:     protected function verifyContextVariableAvailability(
72:         array $variables,
73:         ?string $nodeId,
74:         string $path,
75:         WorkflowDefinitionGraph $graph,
76:         WorkflowVerificationResult $result,
77:     ): void {
78:         $contextVars = array_values(array_filter(
79:             $variables,
80:             fn (string $v): bool => str_starts_with($v, 'context.'),
81:         ));
82: 
83:         if ($contextVars === []) {
84:             return;
85:         }
86: 
87:         $available = $this->collectAvailableContextKeys($nodeId, $graph);
88: 
89:         if ($available === null) {
90:             return;
91:         }
92: 
93:         foreach ($contextVars as $variable) {
94:             $this->validateContextVariableExists($variable, $available, self::CODE_PREFIX, $path, $nodeId, $result);
95:         }
96: 
97:         if ($nodeId !== null) {
98:             $this->warnIfParallelPaths($nodeId, self::CODE_PREFIX, $path, $graph, $result);
99:         }
100:     }
101: 
102:     protected function verifyOutgoingBranches(
103:         ?string $nodeId,
104:         WorkflowDefinitionGraph $graph,
105:         WorkflowVerificationResult $result,
106:     ): void {
107:         if ($nodeId === null || ! $graph->hasNode($nodeId)) {
108:             return;
109:         }
110: 
111:         $count = count($graph->outgoing($nodeId));
112: 
113:         if ($count !== 2) {
114:             $result->addError(
115:                 'if_node.branches_invalid',
116:                 "If node must have exactly 2 outgoing branches, found {$count}.",
117:                 null,
118:                 $nodeId,
119:             );
120:         }
121:     }
122: }

(End of file - total 122 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/ForkNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/ForkNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
7: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
8: 
9: class ForkNodeTypeRule implements NodeTypeRule
10: {
11:     public function nodeType(): string
12:     {
13:         return 'and-node';
14:     }
15: 
16:     public function verify(
17:         array $node,
18:         int $index,
19:         WorkflowDefinitionGraph $graph,
20:         WorkflowVerificationResult $result,
21:         ?Workflow $workflow = null,
22:     ): void {
23:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
24:         $config = is_array($node['config'] ?? null) ? $node['config'] : [];
25:         $branchesPath = "nodes[{$index}].config.branches";
26: 
27:         $raw = is_array($config['branches'] ?? null) ? $config['branches'] : [];
28: 
29:         $validBranches = array_values(array_filter(
30:             $raw,
31:             fn ($b): bool => is_array($b)
32:                 && is_string($b['name'] ?? null) && trim($b['name']) !== ''
33:                 && is_string($b['key'] ?? null) && trim($b['key']) !== '',
34:         ));
35: 
36:         if ($validBranches === []) {
37:             $result->addError(
38:                 'fork.branches_missing',
39:                 'Fork node must have at least one branch with a name and key.',
40:                 $branchesPath,
41:                 $nodeId,
42:             );
43: 
44:             return;
45:         }
46: 
47:         $keys = array_column($validBranches, 'key');
48:         if (count($keys) !== count(array_unique($keys))) {
49:             $result->addError(
50:                 'fork.branches_duplicate_keys',
51:                 'Fork node branches must have unique keys.',
52:                 $branchesPath,
53:                 $nodeId,
54:             );
55:         }
56: 
57:         if ($nodeId !== null && $graph->hasNode($nodeId)) {
58:             $expected = count($validBranches);
59:             $actual = count($graph->outgoing($nodeId));
60: 
61:             if ($actual !== $expected) {
62:                 $result->addError(
63:                     'fork.branches_mismatch',
64:                     "Fork node has {$actual} outgoing branch(es) but needs {$expected} (one per defined branch).",
65:                     null,
66:                     $nodeId,
67:                 );
68:             }
69: 
70:             foreach ($graph->outgoing($nodeId) as $edge) {
71:                 $joinKey = $edge['join_node_key'] ?? null;
72: 
73:                 if (! is_string($joinKey) || trim($joinKey) === '') {
74:                     $result->addError(
75:                         'fork.join_node_key_missing',
76:                         'Every outgoing edge of a fork node must specify join_node_key pointing to its parallel merge.',
77:                         null,
78:                         $nodeId,
79:                         $edge['id'] ?? null,
80:                     );
81: 
82:                     continue;
83:                 }
84: 
85:                 if ($graph->hasNode($joinKey) && $graph->nodeType($joinKey) !== 'merge') {
86:                     $result->addError(
87:                         'fork.join_node_key_invalid',
88:                         "join_node_key '{$joinKey}' must reference a merge node.",
89:                         null,
90:                         $nodeId,
91:                         $edge['id'] ?? null,
92:                     );
93:                 }
94:             }
95:         }
96:     }
97: }

(End of file - total 97 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SwitchNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SwitchNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
7: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
8: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
9: 
10: class SwitchNodeTypeRule implements NodeTypeRule
11: {
12:     use VariableAvailability;
13: 
14:     protected const CODE_PREFIX = 'switch';
15: 
16:     public function nodeType(): string
17:     {
18:         return 'switch';
19:     }
20: 
21:     public function verify(
22:         array $node,
23:         int $index,
24:         WorkflowDefinitionGraph $graph,
25:         WorkflowVerificationResult $result,
26:         ?Workflow $workflow = null,
27:     ): void {
28:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
29:         $config = is_array($node['config'] ?? null) ? $node['config'] : [];
30:         $varPath = "nodes[{$index}].config.variable";
31: 
32:         $variable = trim((string) ($config['variable'] ?? ''));
33: 
34:         if ($variable === '') {
35:             $result->addError('switch.variable_missing', 'Switch node must specify a variable to evaluate.', $varPath, $nodeId);
36:         } else {
37:             $namespaceOk = $this->validateVariableNamespace($variable, self::CODE_PREFIX, $varPath, $nodeId, $result);
38: 
39:             if ($namespaceOk && str_starts_with($variable, 'context.')) {
40:                 $available = $this->collectAvailableContextKeys($nodeId, $graph);
41: 
42:                 if ($available !== null) {
43:                     $this->validateContextVariableExists($variable, $available, self::CODE_PREFIX, $varPath, $nodeId, $result);
44:                 }
45:             }
46: 
47:             if ($namespaceOk && $nodeId !== null) {
48:                 $this->warnIfParallelPaths($nodeId, self::CODE_PREFIX, $varPath, $graph, $result);
49:             }
50:         }
51: 
52:         $this->verifyOptions($config, $index, $nodeId, $graph, $result);
53:     }
54: 
55:     protected function verifyOptions(
56:         array $config,
57:         int $index,
58:         ?string $nodeId,
59:         WorkflowDefinitionGraph $graph,
60:         WorkflowVerificationResult $result,
61:     ): void {
62:         $optionsPath = "nodes[{$index}].config.options";
63:         $raw = is_array($config['options'] ?? null) ? $config['options'] : [];
64: 
65:         $validOptions = array_values(array_filter(
66:             $raw,
67:             fn ($v): bool => is_string($v) && trim($v) !== '',
68:         ));
69: 
70:         if ($validOptions === []) {
71:             $result->addError(
72:                 'switch.options_missing',
73:                 'Switch node must have at least one option to route on.',
74:                 $optionsPath,
75:                 $nodeId,
76:             );
77: 
78:             return;
79:         }
80: 
81:         if ($nodeId !== null && $graph->hasNode($nodeId)) {
82:             $expected = count($validOptions) + 1; // options + 1 default branch
83:             $actual = count($graph->outgoing($nodeId));
84: 
85:             if ($actual !== $expected) {
86:                 $optionCount = count($validOptions);
87:                 $result->addError(
88:                     'switch.branches_mismatch',
89:                     "Switch node has {$actual} outgoing branch(es) but needs {$expected} ({$optionCount} option(s) + 1 default branch).",
90:                     null,
91:                     $nodeId,
92:                 );
93:             }
94:         }
95:     }
96: }

(End of file - total 96 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/MergeNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/MergeNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
7: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
8: 
9: class MergeNodeTypeRule implements NodeTypeRule
10: {
11:     public const VALID_MODES = ['parallel', 'conditional'];
12: 
13:     public const MIN_BRANCHES = 2;
14: 
15:     public function nodeType(): string
16:     {
17:         return 'merge';
18:     }
19: 
20:     public function verify(
21:         array $node,
22:         int $index,
23:         WorkflowDefinitionGraph $graph,
24:         WorkflowVerificationResult $result,
25:         ?Workflow $workflow = null,
26:     ): void {
27:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
28:         $config = is_array($node['config'] ?? null) ? $node['config'] : [];
29: 
30:         $this->verifyMode($config, $index, $nodeId, $result);
31:         $this->verifyBranchCount($config, $index, $nodeId, $graph, $result);
32:     }
33: 
34:     protected function verifyMode(
35:         array $config,
36:         int $index,
37:         ?string $nodeId,
38:         WorkflowVerificationResult $result,
39:     ): void {
40:         $modePath = "nodes[{$index}].config.mergeMode";
41:         $mode = trim((string) ($config['mergeMode'] ?? ''));
42: 
43:         if ($mode === '') {
44:             $result->addError('merge.mode_missing', 'Merge node must specify a merge mode.', $modePath, $nodeId);
45: 
46:             return;
47:         }
48: 
49:         if (! in_array($mode, self::VALID_MODES, true)) {
50:             $result->addError(
51:                 'merge.mode_invalid',
52:                 "Merge node mode must be 'parallel' or 'conditional', got '{$mode}'.",
53:                 $modePath,
54:                 $nodeId,
55:             );
56:         }
57:     }
58: 
59:     protected function verifyBranchCount(
60:         array $config,
61:         int $index,
62:         ?string $nodeId,
63:         WorkflowDefinitionGraph $graph,
64:         WorkflowVerificationResult $result,
65:     ): void {
66:         $countPath = "nodes[{$index}].config.branchCount";
67:         $raw = $config['branchCount'] ?? null;
68: 
69:         if ($raw === null || $raw === '' || ! is_numeric($raw) || (int) $raw != $raw) {
70:             $result->addError(
71:                 'merge.branch_count_missing',
72:                 'Merge node must specify the number of incoming branches.',
73:                 $countPath,
74:                 $nodeId,
75:             );
76: 
77:             return;
78:         }
79: 
80:         $expected = (int) $raw;
81: 
82:         if ($expected < self::MIN_BRANCHES) {
83:             $result->addError(
84:                 'merge.branch_count_invalid',
85:                 'Merge node must have at least '.self::MIN_BRANCHES.' incoming branches.',
86:                 $countPath,
87:                 $nodeId,
88:             );
89: 
90:             return;
91:         }
92: 
93:         if ($nodeId !== null && $graph->hasNode($nodeId)) {
94:             $actual = count($graph->incoming($nodeId));
95: 
96:             if ($actual !== $expected) {
97:                 $result->addError(
98:                     'merge.branches_mismatch',
99:                     "Merge node has {$actual} incoming edge(s) but declares {$expected} branch(es). Wire one branch per declared incoming branch.",
100:                     null,
101:                     $nodeId,
102:                 );
103:             }
104:         }
105:     }
106: }

(End of file - total 106 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/TaskNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/TaskNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
7: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
8: 
9: class TaskNodeTypeRule implements NodeTypeRule
10: {
11:     private const VALID_FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'file', 'checkbox', 'select'];
12: 
13:     private const OPTIONS_REQUIRED_TYPES = ['select', 'checkbox'];
14: 
15:     public function nodeType(): string
16:     {
17:         return 'task-node';
18:     }
19: 
20:     public function verify(
21:         array $node,
22:         int $index,
23:         WorkflowDefinitionGraph $graph,
24:         WorkflowVerificationResult $result,
25:         ?Workflow $workflow = null,
26:     ): void {
27:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
28:         $config = is_array($node['config'] ?? null) ? $node['config'] : [];
29: 
30:         $title = trim((string) ($config['title'] ?? ''));
31:         if ($title === '') {
32:             $result->addError('task_node.title_missing', 'Task node must have a title.', "nodes[{$index}].config.title", $nodeId);
33:         }
34: 
35:         $this->verifyInputFields($config, $index, $nodeId, $result);
36: 
37:         if ($nodeId !== null && $graph->hasNode($nodeId)) {
38:             $count = count($graph->outgoing($nodeId));
39: 
40:             if ($count !== 1) {
41:                 $result->addError(
42:                     'task_node.branches_invalid',
43:                     "Task node must have exactly 1 outgoing branch, found {$count}.",
44:                     null,
45:                     $nodeId,
46:                 );
47:             }
48:         }
49:     }
50: 
51:     private function verifyInputFields(array $config, int $index, ?string $nodeId, WorkflowVerificationResult $result): void
52:     {
53:         $fieldsPath = "nodes[{$index}].config.inputFields";
54:         $inputFields = is_array($config['inputFields'] ?? null) ? $config['inputFields'] : [];
55: 
56:         if (empty($inputFields)) {
57:             $result->addError('task_node.input_fields_missing', 'Task node must have at least one input field.', $fieldsPath, $nodeId);
58: 
59:             return;
60:         }
61: 
62:         foreach ($inputFields as $fieldIdx => $field) {
63:             if (! is_array($field)) {
64:                 $result->addError('task_node.input_field_invalid', 'Input field #'.($fieldIdx + 1).' is not a valid object.', "{$fieldsPath}[{$fieldIdx}]", $nodeId);
65: 
66:                 continue;
67:             }
68: 
69:             $fieldKey = trim((string) ($field['key'] ?? ''));
70:             if ($fieldKey === '') {
71:                 $result->addError('task_node.input_field_key_missing', 'Input field #'.($fieldIdx + 1).' must have a key.', "{$fieldsPath}[{$fieldIdx}].key", $nodeId);
72:             }
73: 
74:             $fieldLabel = trim((string) ($field['label'] ?? ''));
75:             if ($fieldLabel === '') {
76:                 $result->addError('task_node.input_field_label_missing', 'Input field #'.($fieldIdx + 1).' must have a label.', "{$fieldsPath}[{$fieldIdx}].label", $nodeId);
77:             }
78: 
79:             $fieldType = (string) ($field['type'] ?? 'text');
80:             if (! in_array($fieldType, self::VALID_FIELD_TYPES, true)) {
81:                 $result->addError('task_node.input_field_type_invalid', 'Input field #'.($fieldIdx + 1)." has an invalid type '{$fieldType}'.", "{$fieldsPath}[{$fieldIdx}].type", $nodeId);
82:             } elseif (in_array($fieldType, self::OPTIONS_REQUIRED_TYPES, true)) {
83:                 $options = is_array($field['options'] ?? null) ? $field['options'] : [];
84:                 $validOptions = array_filter($options, fn ($o): bool => is_string($o) && trim($o) !== '');
85: 
86:                 if (empty($validOptions)) {
87:                     $result->addError(
88:                         'task_node.input_field_options_missing',
89:                         'Input field #'.($fieldIdx + 1)." of type '{$fieldType}' must have at least one option.",
90:                         "{$fieldsPath}[{$fieldIdx}].options",
91:                         $nodeId,
92:                     );
93:                 }
94:             }
95:         }
96:     }
97: }

(End of file - total 97 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SendEmailNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SendEmailNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
7: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
8: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
9: 
10: class SendEmailNodeTypeRule implements NodeTypeRule
11: {
12:     use VariableAvailability;
13: 
14:     protected const CODE_PREFIX = 'send_email';
15: 
16:     public function nodeType(): string
17:     {
18:         return 'send-email';
19:     }
20: 
21:     public function verify(
22:         array $node,
23:         int $index,
24:         WorkflowDefinitionGraph $graph,
25:         WorkflowVerificationResult $result,
26:         ?Workflow $workflow = null,
27:     ): void {
28:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
29:         $config = is_array($node['config'] ?? null) ? $node['config'] : [];
30: 
31:         $to = trim((string) ($config['to'] ?? ''));
32:         if ($to === '') {
33:             $result->addError('send_email.to_missing', 'Send email node must specify a recipient.', "nodes[{$index}].config.to", $nodeId);
34:         } else {
35:             $this->validateEmailOrVar($to, "nodes[{$index}].config.to", 'to', $nodeId, $graph, $result);
36:         }
37: 
38:         foreach (['cc', 'bcc'] as $field) {
39:             $val = trim((string) ($config[$field] ?? ''));
40:             if ($val !== '') {
41:                 $this->validateEmailOrVar($val, "nodes[{$index}].config.{$field}", $field, $nodeId, $graph, $result);
42:             }
43:         }
44: 
45:         $subject = trim((string) ($config['subject'] ?? ''));
46:         if ($subject === '') {
47:             $result->addError('send_email.subject_missing', 'Send email node must have a subject.', "nodes[{$index}].config.subject", $nodeId);
48:         } else {
49:             $this->validateTemplateVariables($subject, "nodes[{$index}].config.subject", $nodeId, self::CODE_PREFIX, $graph, $result);
50:         }
51: 
52:         $body = trim((string) ($config['body'] ?? ''));
53:         if ($body === '') {
54:             $result->addError('send_email.body_missing', 'Send email node must have a body.', "nodes[{$index}].config.body", $nodeId);
55:         } else {
56:             $this->validateTemplateVariables($body, "nodes[{$index}].config.body", $nodeId, self::CODE_PREFIX, $graph, $result);
57:         }
58:     }
59: 
60:     /**
61:      * Value must be either a single {{context.<key>}} / {{customer.<key>}} variable
62:      * or a plain email address. Nothing else is accepted.
63:      */
64:     private function validateEmailOrVar(
65:         string $value,
66:         string $path,
67:         string $fieldName,
68:         ?string $nodeId,
69:         WorkflowDefinitionGraph $graph,
70:         WorkflowVerificationResult $result,
71:     ): void {
72:         if (str_starts_with($value, '{{')) {
73:             // Must be exactly one complete template variable and nothing else
74:             if (! preg_match('/^\{\{\s*(context|customer)\.([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}$/', $value, $m)) {
75:                 $result->addError(
76:                     "send_email.{$fieldName}_invalid",
77:                     "The {$fieldName} field has an invalid variable '{$value}'. Use {{context.<key>}} or {{customer.<key>}} with a single identifier.",
78:                     $path,
79:                     $nodeId,
80:                 );
81: 
82:                 return;
83:             }
84: 
85:             $variable = trim(substr($value, 2, -2)); // strip {{ }}
86:             if (str_starts_with($variable, 'context.') && $nodeId !== null) {
87:                 $available = $this->collectAvailableContextKeys($nodeId, $graph);
88:                 if ($available !== null) {
89:                     $this->validateContextVariableExists($variable, $available, self::CODE_PREFIX, $path, $nodeId, $result);
90:                 }
91:             }
92:         } elseif (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
93:             $result->addError(
94:                 "send_email.{$fieldName}_invalid",
95:                 "The {$fieldName} field must be a valid email address or a variable like {{context.email}}.",
96:                 $path,
97:                 $nodeId,
98:             );
99:         }
100:     }
101: }

(End of file - total 101 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/AiGeneratorNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/AiGeneratorNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
7: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
8: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
9: 
10: class AiGeneratorNodeTypeRule implements NodeTypeRule
11: {
12:     use VariableAvailability;
13: 
14:     protected const CODE_PREFIX = 'ai_generator';
15: 
16:     public function nodeType(): string
17:     {
18:         return 'ai-generator';
19:     }
20: 
21:     public function verify(
22:         array $node,
23:         int $index,
24:         WorkflowDefinitionGraph $graph,
25:         WorkflowVerificationResult $result,
26:         ?Workflow $workflow = null,
27:     ): void {
28:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
29:         $config = is_array($node['config'] ?? null) ? $node['config'] : [];
30: 
31:         $prompt = trim((string) ($config['prompt'] ?? ''));
32:         if ($prompt === '') {
33:             $result->addError(
34:                 'ai_generator.prompt_missing',
35:                 'AI Generator node must have a prompt.',
36:                 "nodes[{$index}].config.prompt",
37:                 $nodeId,
38:             );
39:         } else {
40:             $this->validateTemplateVariables($prompt, "nodes[{$index}].config.prompt", $nodeId, self::CODE_PREFIX, $graph, $result);
41:         }
42: 
43:         $outputVar = trim((string) ($config['outputVariable'] ?? ''));
44:         if ($outputVar !== '' && ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $outputVar)) {
45:             $result->addError(
46:                 'ai_generator.output_variable_invalid',
47:                 "Output variable name '{$outputVar}' must be a valid identifier (letters, numbers, underscore; cannot start with a digit).",
48:                 "nodes[{$index}].config.outputVariable",
49:                 $nodeId,
50:             );
51:         }
52:     }
53: }

(End of file - total 53 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/TerminationNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/TerminationNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
7: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
8: 
9: class TerminationNodeTypeRule implements NodeTypeRule
10: {
11:     protected const CODE_PREFIX = 'termination_node';
12: 
13:     public function nodeType(): string
14:     {
15:         return 'termination-node';
16:     }
17: 
18:     public function verify(
19:         array $node,
20:         int $index,
21:         WorkflowDefinitionGraph $graph,
22:         WorkflowVerificationResult $result,
23:         ?Workflow $workflow = null,
24:     ): void {
25:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
26: 
27:         if ($nodeId !== null && $graph->outgoing($nodeId) !== []) {
28:             $result->addError(
29:                 'termination_node.has_outgoing_edges',
30:                 'A Termination node must not have outgoing edges.',
31:                 "nodes[{$index}]",
32:                 $nodeId,
33:             );
34:         }
35:     }
36: }

(End of file - total 36 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SubWorkflowNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/SubWorkflowNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\Rules\NodeType\Concerns\VariableAvailability;
7: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
8: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
9: 
10: class SubWorkflowNodeTypeRule implements NodeTypeRule
11: {
12:     use VariableAvailability;
13: 
14:     protected const CODE_PREFIX = 'sub_workflow';
15: 
16:     public function nodeType(): string
17:     {
18:         return 'sub-workflow';
19:     }
20: 
21:     public function verify(
22:         array $node,
23:         int $index,
24:         WorkflowDefinitionGraph $graph,
25:         WorkflowVerificationResult $result,
26:         ?Workflow $workflow = null,
27:     ): void {
28:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
29:         $config = is_array($node['config'] ?? null) ? $node['config'] : [];
30:         $path = "nodes[{$index}].config";
31: 
32:         $childWorkflowId = $config['workflowId'] ?? null;
33: 
34:         if ($childWorkflowId === null || $childWorkflowId === '' || ! is_numeric($childWorkflowId)) {
35:             $result->addError(
36:                 'sub_workflow.workflow_id_missing',
37:                 'Sub-workflow node must specify a valid workflowId.',
38:                 "{$path}.workflowId",
39:                 $nodeId,
40:             );
41: 
42:             return;
43:         }
44: 
45:         $childWorkflow = Workflow::query()->find((int) $childWorkflowId);
46: 
47:         if ($childWorkflow === null) {
48:             $result->addError(
49:                 'sub_workflow.workflow_not_found',
50:                 "Workflow #{$childWorkflowId} does not exist.",
51:                 "{$path}.workflowId",
52:                 $nodeId,
53:             );
54: 
55:             return;
56:         }
57: 
58:         if ($childWorkflow->current_version_id === null) {
59:             $result->addError(
60:                 'sub_workflow.workflow_not_published',
61:                 "Workflow #{$childWorkflowId} has not been published.",
62:                 "{$path}.workflowId",
63:                 $nodeId,
64:             );
65:         }
66: 
67:         if ($workflow !== null && (int) $childWorkflow->tenant_id !== (int) $workflow->tenant_id) {
68:             $result->addError(
69:                 'sub_workflow.tenant_mismatch',
70:                 "Sub-workflow #{$childWorkflowId} belongs to a different tenant.",
71:                 "{$path}.workflowId",
72:                 $nodeId,
73:             );
74:         }
75: 
76:         if ($workflow !== null && (int) $childWorkflow->id === (int) $workflow->id) {
77:             $result->addError(
78:                 'sub_workflow.self_reference',
79:                 'A workflow cannot reference itself as a sub-workflow.',
80:                 "{$path}.workflowId",
81:                 $nodeId,
82:             );
83:         }
84: 
85:         // Validate inputMapping template expressions (syntax + variable existence)
86:         $inputMapping = $config['inputMapping'] ?? [];
87:         if (is_array($inputMapping)) {
88:             foreach ($inputMapping as $childKey => $template) {
89:                 if (! is_string($template)) {
90:                     continue;
91:                 }
92: 
93:                 // Detect unclosed template expressions (has {{ but no matching }})
94:                 if (preg_match('/\{\{/', $template) && ! preg_match('/\{\{[^}]+\}\}/', $template)) {
95:                     $result->addError(
96:                         self::CODE_PREFIX.'.unclosed_template',
97:                         "Input mapping for \"{$childKey}\": Unclosed template expression. Use {{context.<key>}}.",
98:                         "{$path}.inputMapping.{$childKey}",
99:                         $nodeId,
100:                     );
101: 
102:                     continue;
103:                 }
104: 
105:                 $this->validateTemplateVariables(
106:                     $template,
107:                     "{$path}.inputMapping.{$childKey}",
108:                     $nodeId,
109:                     self::CODE_PREFIX,
110:                     $graph,
111:                     $result,
112:                 );
113:             }
114:         }
115:     }
116: }

(End of file - total 116 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/DynamicEntryNodeTypeRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/DynamicEntryNodeTypeRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType;
4: 
5: use Modules\Workflows\Models\Workflow;
6: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
7: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
8: 
9: class DynamicEntryNodeTypeRule implements NodeTypeRule
10: {
11:     protected const CODE_PREFIX = 'dynamic_entry';
12: 
13:     public function nodeType(): string
14:     {
15:         return 'dynamic-entry';
16:     }
17: 
18:     public function verify(
19:         array $node,
20:         int $index,
21:         WorkflowDefinitionGraph $graph,
22:         WorkflowVerificationResult $result,
23:         ?Workflow $workflow = null,
24:     ): void {
25:         $nodeId = is_string($node['id'] ?? null) ? $node['id'] : null;
26: 
27:         if ($nodeId === null) {
28:             return;
29:         }
30: 
31:         // Must have no incoming edges — it is the entry point.
32:         if ($graph->incoming($nodeId) !== []) {
33:             $result->addError(
34:                 'dynamic_entry.has_incoming_edges',
35:                 'An Entry Point node must not have incoming edges.',
36:                 "nodes[{$index}]",
37:                 $nodeId,
38:             );
39:         }
40: 
41:         // Must have at least one outgoing edge.
42:         if ($graph->outgoing($nodeId) === []) {
43:             $result->addError(
44:                 'dynamic_entry.no_outgoing_edges',
45:                 'An Entry Point node must have at least one outgoing edge.',
46:                 "nodes[{$index}]",
47:                 $nodeId,
48:             );
49:         }
50:     }
51: }

(End of file - total 51 lines)
</content>
```

---

## Assistant (Plan · DeepSeek V4 Flash Free · 4.4s)

_Thinking:_

Now let me read the VariableAvailability trait to understand the shared logic:

Now let me also read the trait and check for any other files that depend on the service provider registration pattern.

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/Concerns/VariableAvailability.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/Concerns/VariableAvailability.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules\NodeType\Concerns;
4: 
5: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
6: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
7: 
8: trait VariableAvailability
9: {
10:     protected function validateVariableNamespace(
11:         string $variable,
12:         string $codePrefix,
13:         string $path,
14:         ?string $nodeId,
15:         WorkflowVerificationResult $result,
16:     ): bool {
17:         if (! str_starts_with($variable, 'context.') && ! str_starts_with($variable, 'customer.')) {
18:             $result->addError(
19:                 "{$codePrefix}.variable_invalid_namespace",
20:                 "Variable '{$variable}' must be prefixed with 'context.' or 'customer.' (e.g. context.status).",
21:                 $path,
22:                 $nodeId,
23:             );
24: 
25:             return false;
26:         }
27: 
28:         return true;
29:     }
30: 
31:     protected function validateContextVariableExists(
32:         string $variable,
33:         array $available,
34:         string $codePrefix,
35:         string $path,
36:         ?string $nodeId,
37:         WorkflowVerificationResult $result,
38:     ): void {
39:         if (! in_array($variable, $available, true)) {
40:             $hint = $available !== []
41:                 ? ' Available: '.implode(', ', $available).'.'
42:                 : ' No context variables are defined by the trigger.';
43: 
44:             $result->addError(
45:                 "{$codePrefix}.variable_undefined",
46:                 "Variable '{$variable}' is not defined in the workflow context.{$hint}",
47:                 $path,
48:                 $nodeId,
49:             );
50:         }
51:     }
52: 
53:     protected function warnIfParallelPaths(
54:         string $nodeId,
55:         string $codePrefix,
56:         string $path,
57:         WorkflowDefinitionGraph $graph,
58:         WorkflowVerificationResult $result,
59:     ): void {
60:         if ($this->hasParallelPaths($nodeId, $graph)) {
61:             $result->addWarning(
62:                 "{$codePrefix}.parallel_paths",
63:                 'This node is reachable via multiple execution paths. Variables set by intermediate nodes on a specific branch may not be available when execution arrives via a different branch.',
64:                 $path,
65:                 $nodeId,
66:             );
67:         }
68:     }
69: 
70:     /**
71:      * Collect context.* variable keys available at $nodeId via the trigger.
72:      * Returns null when the available keys cannot be determined (unsupported trigger type).
73:      *
74:      * @return list<string>|null
75:      */
76:     protected function collectAvailableContextKeys(?string $nodeId, WorkflowDefinitionGraph $graph): ?array
77:     {
78:         if ($nodeId === null) {
79:             return null;
80:         }
81: 
82:         $definition = $graph->definition();
83:         $triggerType = $definition['trigger']['type'] ?? null;
84: 
85:         if ($triggerType !== 'manual-trigger') {
86:             return null;
87:         }
88: 
89:         $triggerNodeId = $graph->triggerNodeId();
90: 
91:         if ($triggerNodeId === null || ! in_array($triggerNodeId, $graph->ancestorNodeIds($nodeId), true)) {
92:             return null;
93:         }
94: 
95:         $rawVariables = $definition['trigger']['config']['variables'] ?? [];
96: 
97:         if (! is_array($rawVariables)) {
98:             return [];
99:         }
100: 
101:         $keys = [];
102:         foreach ($rawVariables as $var) {
103:             if (is_array($var) && isset($var['key']) && is_string($var['key']) && $var['key'] !== '') {
104:                 $keys[] = 'context.'.$var['key'];
105:             }
106:         }
107: 
108:         return $keys;
109:     }
110: 
111:     protected function validateTemplateVariables(
112:         string $text,
113:         string $path,
114:         ?string $nodeId,
115:         string $codePrefix,
116:         WorkflowDefinitionGraph $graph,
117:         WorkflowVerificationResult $result,
118:     ): void {
119:         preg_match_all('/\{\{([^}]+)\}\}/', $text, $matches);
120: 
121:         if (empty($matches[1])) {
122:             return;
123:         }
124: 
125:         $available = $nodeId !== null ? $this->collectAvailableContextKeys($nodeId, $graph) : null;
126: 
127:         foreach ($matches[1] as $raw) {
128:             $variable = trim($raw);
129: 
130:             if (! preg_match('/^(context|customer)\.[a-zA-Z_][a-zA-Z0-9_]*$/', $variable)) {
131:                 $result->addError(
132:                     "{$codePrefix}.invalid_template_variable",
133:                     "Template variable '{{{{{$variable}}}}}' is invalid. Use {{context.<key>}} or {{customer.<key>}} with a single identifier.",
134:                     $path,
135:                     $nodeId,
136:                 );
137: 
138:                 continue;
139:             }
140: 
141:             if (str_starts_with($variable, 'context.') && $available !== null) {
142:                 $this->validateContextVariableExists($variable, $available, $codePrefix, $path, $nodeId, $result);
143:             }
144:         }
145:     }
146: 
147:     protected function hasParallelPaths(string $nodeId, WorkflowDefinitionGraph $graph): bool
148:     {
149:         foreach ($graph->ancestorNodeIds($nodeId) as $ancestorId) {
150:             $outgoing = $graph->outgoing($ancestorId);
151: 
152:             if (count($outgoing) <= 1) {
153:                 continue;
154:             }
155: 
156:             $pathsLeadingToNode = 0;
157: 
158:             foreach ($outgoing as $edge) {
159:                 $target = $edge['target_node_key'] ?? null;
160: 
161:                 if (! is_string($target)) {
162:                     continue;
163:                 }
164: 
165:                 if ($target === $nodeId || in_array($nodeId, $graph->reachableFrom($target), true)) {
166:                     $pathsLeadingToNode++;
167:                 }
168:             }
169: 
170:             if ($pathsLeadingToNode > 1) {
171:                 return true;
172:             }
173:         }
174: 
175:         return false;
176:     }
177: }

(End of file - total 177 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/VerificationRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/VerificationRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules;
4: 
5: use Modules\Auth\Models\User;
6: use Modules\Workflows\Enums\VerificationMode;
7: use Modules\Workflows\Models\Workflow;
8: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
9: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
10: 
11: interface VerificationRule
12: {
13:     public function verify(
14:         array $definition,
15:         WorkflowDefinitionGraph $graph,
16:         WorkflowVerificationResult $result,
17:         ?Workflow $workflow = null,
18:         ?User $actor = null,
19:         VerificationMode $mode = VerificationMode::Full,
20:     ): void;
21: }

(End of file - total 21 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ExpressionValidationResult.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ExpressionValidationResult.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification;
4: 
5: class ExpressionValidationResult
6: {
7:     /**
8:      * @param  list<string>  $variables
9:      */
10:     public function __construct(
11:         public readonly bool $valid,
12:         public readonly bool $boolean,
13:         public readonly array $variables = [],
14:         public readonly ?string $message = null,
15:     ) {}
16: }

(End of file - total 16 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/ExpressionVerificationRule.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/ExpressionVerificationRule.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Modules\Workflows\Services\Verification\Rules;
4: 
5: use Modules\Auth\Models\User;
6: use Modules\Workflows\Enums\VerificationMode;
7: use Modules\Workflows\Models\Workflow;
8: use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
9: use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
10: use Modules\Workflows\Services\Verification\WorkflowVerificationResult;
11: 
12: class ExpressionVerificationRule implements VerificationRule
13: {
14:     public function __construct(
15:         protected ExpressionLanguageValidator $expressionValidator
16:     ) {}
17: 
18:     public function verify(
19:         array $definition,
20:         WorkflowDefinitionGraph $graph,
21:         WorkflowVerificationResult $result,
22:         ?Workflow $workflow = null,
23:         ?User $actor = null,
24:         VerificationMode $mode = VerificationMode::Full,
25:     ): void {
26:         $this->verifyEdgeExpressions($graph, $result);
27:         $this->verifyNodeExpressions($graph, $result);
28:     }
29: 
30:     protected function verifyEdgeExpressions(WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result): void
31:     {
32:         foreach ($graph->edges() as $index => $edge) {
33:             if (($edge['branch_type'] ?? 'default') !== 'conditional' || (bool) ($edge['is_default_branch'] ?? false)) {
34:                 continue;
35:             }
36: 
37:             $expression = trim((string) ($edge['condition_expression'] ?? ''));
38:             $edgeId = $edge['id'] ?? null;
39:             $path = "edges[{$index}].condition_expression";
40: 
41:             if ($expression === '') {
42:                 $result->addError('expression.condition_missing', 'Conditional edge must declare condition_expression.', $path, null, $edgeId);
43: 
44:                 continue;
45:             }
46: 
47:             $validation = $this->expressionValidator->validateBoolean($expression);
48: 
49:             if (! $validation->valid) {
50:                 $result->addError(
51:                     'expression.condition_invalid',
52:                     'Conditional edge expression is invalid: '.$validation->message,
53:                     $path,
54:                     null,
55:                     $edgeId
56:                 );
57:             }
58:         }
59:     }
60: 
61:     protected function verifyNodeExpressions(WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result): void
62:     {
63:         foreach ($graph->nodes() as $index => $node) {
64:             $config = $node['config'] ?? [];
65:             $nodeId = $node['id'] ?? null;
66: 
67:             foreach (['expression', 'condition'] as $configKey) {
68:                 if (! isset($config[$configKey]) || ! is_string($config[$configKey]) || trim($config[$configKey]) === '') {
69:                     continue;
70:                 }
71: 
72:                 $validation = $this->expressionValidator->validateBoolean($config[$configKey]);
73: 
74:                 if (! $validation->valid) {
75:                     $result->addError(
76:                         'expression.node_invalid',
77:                         "Node expression '{$configKey}' is invalid: ".$validation->message,
78:                         "nodes[{$index}].config.{$configKey}",
79:                         is_string($nodeId) ? $nodeId : null
80:                     );
81:                 }
82:             }
83:         }
84:     }
85: }

(End of file - total 85 lines)
</content>
```

**Tool: glob**

**Input:**
```json
{
  "pattern": "Modules/Workflows/app/Providers/*.php"
}
```

**Output:**
```
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Providers/RouteServiceProvider.php
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Providers/WorkflowsServiceProvider.php
```

**Tool: glob**

**Input:**
```json
{
  "pattern": "Modules/Workflows/app/Services/Execution/Expression/*.php"
}
```

**Output:**
```
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Expression/TemplateInterpolator.php
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Expression/ExpressionEvaluator.php
```

---

## Assistant (Plan · DeepSeek V4 Flash Free · 12.3s)

_Thinking:_

Now I have a complete picture. Let me also check the BaseService that these services extend and verify how the tag-based injection would work. Let me also check if there are any test files that reference these services so I know what might need updating.

Let me also look at the `VariableAvailability` trait more closely - the `collectAvailableContextKeys` only handles `manual-trigger` and returns `null` for everything else. The review mentioned this is a functional gap.

Now let me think through the implementation plans for issues 2 and 3:

## Issue 2: Tag-based NodeTypeRule injection

Currently `WorkflowVerificationService` injects all 10 node type rules individually and manually registers them into `NodeTypeVerificationRule::register()`. The fix:

1. Add a tag constant (e.g., `'node-type-rule'`) in the service provider
2. Tag all 10 `NodeTypeRule` implementations in `register()` method of `WorkflowsServiceProvider`
3. Change `NodeTypeVerificationRule` to accept `iterable $rules` in its constructor instead of having a `register()` method
4. Remove all 10 individual node type rule injections from `WorkflowVerificationService` constructor
5. Remove the manual registration loop in the constructor body

## Issue 3: Extract shared expression grammar

The `ExpressionLanguageValidator` and `ExpressionEvaluator` both have:
- Same `tokenize()` method (identical regex-based tokenization)
- Same grammar structure: `parseOr() -> parseAnd() -> parseComparison() -> parseUnary() -> parsePrimary()`
- Same `consumeOperator()`, `consumeAnyOperator()`, `next()`, `atEnd()`, `currentValue()` methods

The difference is what the parser produces:
- `ExpressionLanguageValidator` returns a type string ('boolean', 'variable', 'number', 'string', 'null')
- `ExpressionEvaluator` returns the actual evaluated value (resolved against `$data`)

The plan:
1. Extract a shared `ExpressionLexer` class that handles tokenization and cursor management
2. Both classes use the lexer instead of managing their own token arrays
3. The grammar structure stays in each class, but they both delegate to the shared lexer for token management

Actually, a simpler approach would be:
1. Extract the `tokenize()` and all cursor methods (`consumeOperator`, `next`, `atEnd`, `currentValue`) into a shared `ExpressionLexer` or `ExpressionTokenStream`
2. Both the validator and evaluator accept this lexer/stream
3. Create a static factory or constructor that builds it from a string

Let me think about this more carefully...

The cleanest approach:
1. Create an `ExpressionLexer` class that:
   - Takes an expression string in its constructor and tokenizes it
   - Provides `consumeOperator(string $operator): bool`
   - Provides `consumeAnyOperator(array $operators): ?string`
   - Provides `next(): ?array`
   - Provides `atEnd(): bool`
   - Provides `currentValue(): string`
   - Provides `tokens(): array` (for debugging)

2. Both `ExpressionLanguageValidator` and `ExpressionEvaluator`:
   - Take `ExpressionLexer` in `__construct` OR have a factory that creates it
   - The lexer is stateless (the cursor is internal)

Actually, looking more carefully, since PHP passes objects by reference, the cursor state would mean the lexer is stateful. But we can pass it as a parameter instead.

Let me think about the design:

```php
class ExpressionLexer 
{
    public function __construct(private string $expression) 
    {
        $this->tokens = $this->tokenize($expression);
        $this->position = 0;
    }
    
    // Cursor methods
    public function consumeOperator(string $operator): bool { ... }
    public function consumeAnyOperator(array $operators): ?string { ... }
    public function next(): ?array { ... }
    public function atEnd(): bool { ... }
    public function currentValue(): string { ... }
    
    // Token access
    public function tokens(): array { ... }
}
```

Then both classes create a lexer per call:
```php
// In ExpressionLanguageValidator:
public function validateBoolean(string $expression): ExpressionValidationResult
{
    try {
        $lexer = new ExpressionLexer($expression);
        return $this->parseOr($lexer);
    }
}
```

And the parse methods take the lexer as a parameter:
```php
protected function parseOr(ExpressionLexer $lexer): string
{
    $type = $this->parseAnd($lexer);
    while ($lexer->consumeOperator('||')) { ... }
    return $type;
}
```

This is a clean extraction. The grammar parsing stays in each class but the tokenization and cursor management is shared.

Let me also think about the `TemplateInterpolator` - it uses `ExpressionEvaluator` to evaluate each `{{ }}` placeholder. That doesn't change.

Now let me put together the comprehensive plan for the user. I shouldn't make any changes since we're in plan mode.

Let me also look at what else references `WorkflowVerificationService` to understand the full impact.

Let me check one more thing — which files actually call the verification service and the expression validator/evaluator to plan the impact.

**Tool: grep**

**Input:**
```json
{
  "pattern": "WorkflowVerificationService",
  "include": "*.php",
  "path": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app"
}
```

**Output:**
```
Found 9 matches
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Providers/WorkflowsServiceProvider.php:
  Line 44: use Modules\Workflows\Services\WorkflowVerificationService;

  Line 88:         $this->app->singleton(WorkflowVerificationService::class);


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVerificationService.php:
  Line 30: class WorkflowVerificationService


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowManagementService.php:
  Line 20:         protected WorkflowVerificationService $verificationService,


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/WorkflowController.php:
  Line 18: use Modules\Workflows\Services\WorkflowVerificationService;

  Line 28:         protected WorkflowVerificationService $verificationService,


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Http/Controllers/DynamicFlowController.php:
  Line 19: use Modules\Workflows\Services\WorkflowVerificationService;

  Line 24:         protected WorkflowVerificationService $validationService,


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVersioningService.php:
  Line 16:         protected WorkflowVerificationService $verificationService,

```

**Tool: grep**

**Input:**
```json
{
  "pattern": "ExpressionLanguageValidator|ExpressionEvaluator",
  "include": "*.php",
  "path": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app"
}
```

**Output:**
```
Found 18 matches
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Providers/WorkflowsServiceProvider.php:
  Line 29: use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;

  Line 37: use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;

  Line 83:         $this->app->singleton(ExpressionLanguageValidator::class);

  Line 92:         $this->app->singleton(ExpressionEvaluator::class);


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/ExpressionLanguageValidator.php:
  Line 5: class ExpressionLanguageValidator


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/WorkflowRuntime.php:
  Line 28: use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;

  Line 44:         protected ExpressionEvaluator $evaluator,


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Expression/TemplateInterpolator.php:
  Line 10:  * against the runtime context. Each placeholder body is evaluated by {@see ExpressionEvaluator}, so the

  Line 16:     public function __construct(protected ExpressionEvaluator $evaluator) {}


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/Expression/ExpressionEvaluator.php:
  Line 5: use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;

  Line 11:  * The grammar mirrors {@see ExpressionLanguageValidator} exactly

  Line 20: class ExpressionEvaluator


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Execution/NodeExecutionContext.php:
  Line 7: use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;

  Line 23:         protected ExpressionEvaluator $evaluator,


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/ExpressionVerificationRule.php:
  Line 8: use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;

  Line 15:         protected ExpressionLanguageValidator $expressionValidator


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeType/IfNodeTypeRule.php:
  Line 6: use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;

  Line 18:         protected ExpressionLanguageValidator $expressionValidator,

```

**Tool: grep**

**Input:**
```json
{
  "pattern": "NodeTypeVerificationRule",
  "include": "*.php",
  "path": "/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows"
}
```

**Output:**
```
Found 3 matches
/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/WorkflowVerificationService.php:
  Line 23: use Modules\Workflows\Services\Verification\Rules\NodeTypeVerificationRule;

  Line 39:         protected NodeTypeVerificationRule $nodeTypeRule,


/home/huda/No-Code-Workflow-Automation-Platform/Modules/Workflows/app/Services/Verification/Rules/NodeTypeVerificationRule.php:
  Line 12: class NodeTypeVerificationRule implements VerificationRule

```

---

## Assistant (Plan · DeepSeek V4 Flash Free · 24.9s)

_Thinking:_

Now I have a complete understanding. Let me also check the test files for these services so I know what might need updating.

Let me now formulate the complete plan for issues 2 and 3.

For Issue 2, the plan is:
1. In `WorkflowsServiceProvider::register()`, tag all 10 `NodeTypeRule` implementations
2. In `NodeTypeVerificationRule`, accept an `iterable $rules` in constructor instead of the `register()` method
3. In `WorkflowVerificationService`, remove the 10 individual node type rule constructor parameters and the manual registration loop

For Issue 3, the plan is:
1. Create `ExpressionLexer` class in the Verification namespace (shared between both)
2. Update `ExpressionLanguageValidator` to use `ExpressionLexer`
3. Update `ExpressionEvaluator` to use `ExpressionLexer`

Let me think about where the `ExpressionLexer` should live. Since both `ExpressionLanguageValidator` (verification namespace) and `ExpressionEvaluator` (execution namespace) need it, it should go in a shared location. Options:
- A new `Modules/Workflows/Services/Expression/Lexer.php` at the shared service level
- Put it in verification and have the evaluator reference it
- Put a copy in each (not DRY)

The cleanest would be to put it at `Modules/Workflows/Services/Expression/ExpressionLexer.php` since both verification and execution are sub-namespaces.

Wait, actually, looking at the namespaces more carefully:
- `Modules\Workflows\Services\Verification\ExpressionLanguageValidator`
- `Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator`

A shared location could be `Modules\Workflows\Services\Expression\ExpressionLexer` with namespace `Modules\Workflows\Services\Expression`.

Now let me write up the complete plan.

Now I have the full picture. Here's the implementation plan.

---

## Plan: Issue 2 — Tag-based NodeTypeRule injection

### Goal
Eliminate the 10 individual `NodeTypeRule` constructor parameters from `WorkflowVerificationService` (cutting deps from 18 → ~8) and remove the manual registration loop.

### Steps

**A) Tag all 10 `NodeTypeRule` implementations in `WorkflowsServiceProvider::register()`**

After the existing singletons, add the tagged binding:

```php
$this->app->tag([
    IfNodeTypeRule::class,
    ForkNodeTypeRule::class,
    SwitchNodeTypeRule::class,
    MergeNodeTypeRule::class,
    TaskNodeTypeRule::class,
    SendEmailNodeTypeRule::class,
    AiGeneratorNodeTypeRule::class,
    TerminationNodeTypeRule::class,
    SubWorkflowNodeTypeRule::class,
    DynamicEntryNodeTypeRule::class,
], 'node-type-rules');
```

You'll also need to add the corresponding `use` imports for all 10 classes (they're already imported in `WorkflowVerificationService` so you can copy from there).

**B) Rewrite `NodeTypeVerificationRule` to accept tagged rules via constructor**

Replace:
```php
class NodeTypeVerificationRule implements VerificationRule
{
    /** @var array<string, NodeTypeRule> */
    protected array $rules = [];

    public function register(NodeTypeRule $rule): void
    {
        $this->rules[$rule->nodeType()] = $rule;
    }
    // ...
}
```

With:
```php
class NodeTypeVerificationRule implements VerificationRule
{
    /** @var array<string, NodeTypeRule> */
    protected array $rules = [];

    public function __construct(iterable $rules)
    {
        foreach ($rules as $rule) {
            $this->rules[$rule->nodeType()] = $rule;
        }
    }
    // remove register() method
}
```

Laravel will resolve `iterable $rules` from the `node-type-rules` tag automatically.

**C) Prune `WorkflowVerificationService` constructor**

Remove these 10 parameters:
- `IfNodeTypeRule $ifNodeTypeRule`
- `ForkNodeTypeRule $forkNodeTypeRule`
- `SwitchNodeTypeRule $switchNodeTypeRule`
- `MergeNodeTypeRule $mergeNodeTypeRule`
- `TaskNodeTypeRule $taskNodeTypeRule`
- `SendEmailNodeTypeRule $sendEmailNodeTypeRule`
- `AiGeneratorNodeTypeRule $aiGeneratorNodeTypeRule`
- `TerminationNodeTypeRule $terminationNodeTypeRule`
- `SubWorkflowNodeTypeRule $subWorkflowNodeTypeRule`
- `DynamicEntryNodeTypeRule $dynamicEntryNodeTypeRule`

Remove the entire constructor body (the 10 `$this->nodeTypeRule->register()` calls).

Remove the corresponding 10 `use` imports.

**D) Add `use` imports in `WorkflowsServiceProvider`**

Add imports for all 10 NodeTypeRule classes (needed to reference them in `->tag()`).

### Files changed
1. `app/Services/Verification/Rules/NodeTypeVerificationRule.php`
2. `app/Services/WorkflowVerificationService.php`
3. `app/Providers/WorkflowsServiceProvider.php`

---

## Plan: Issue 3 — Shared expression grammar

### Goal
Eliminate the duplicated `tokenize()` method and all cursor-management methods (`consumeOperator`, `consumeAnyOperator`, `next`, `atEnd`, `currentValue`) that exist in both `ExpressionLanguageValidator` (241 lines) and `ExpressionEvaluator` (362 lines). Extract them into a shared `ExpressionLexer`.

### What's duplicated (exact same code in both classes)
- `tokenize()` — identical regex logic, same grammar
- `consumeOperator()` — identical
- `consumeAnyOperator()` — identical
- `next()` — identical
- `atEnd()` — identical
- `currentValue()` — identical
- Instance properties `$tokens`, `$position` — identical structure

### What differs between the two
- **`ExpressionLanguageValidator`**: parse methods return a **type string** (`'boolean'`, `'variable'`, `'number'`, `'string'`, `'null'`); no data resolution
- **`ExpressionEvaluator`**: parse methods return **runtime values** (resolved from `$data`); has short-circuit evaluation (`skipAnd()`, `skipComparison()`), `toBool()`, `compare()` etc.

### Steps

**A) Create `Modules/Workflows/app/Services/Expression/ExpressionLexer.php`**

Namespace: `Modules\Workflows\Services\Expression`

```php
class ExpressionLexer
{
    /** @var array<int, array{type: string, value: string}> */
    private array $tokens;

    private int $position = 0;

    public function __construct(string $expression)
    {
        $this->tokens = $this->tokenize($expression);
    }

    // --- Cursor ---
    public function consumeOperator(string $operator): bool { /* code from either class */ }
    public function consumeAnyOperator(array $operators): ?string { /* code */ }
    public function next(): ?array { /* code */ }
    public function atEnd(): bool { /* code */ }
    public function currentValue(): string { /* code */ }

    // --- Internal ---
    private function tokenize(string $expression): array { /* shared tokenize() */ }
}
```

**B) Update `ExpressionLanguageValidator`**

- Remove `$tokens`, `$position` instance properties
- In `validateBoolean()`, create `$lexer = new ExpressionLexer($expression)` instead of calling `$this->tokenize()`
- All parse methods (`parseOr`, `parseAnd`, `parseComparison`, `parseUnary`, `parsePrimary`) now take `ExpressionLexer $lexer` as their first parameter
- Replace all `$this->consumeOperator(...)` calls with `$lexer->consumeOperator(...)`
- Keep the type-returning logic (it's the unique responsibility of the validator)

**C) Update `ExpressionEvaluator`**

- Remove `$tokens`, `$position` instance properties
- Remove `$data` instance property (keep as method parameter — already is one)
- In `evaluate()`, create `$lexer = new ExpressionLexer($expression)` instead of calling `$this->tokenize()`
- All parse methods take `ExpressionLexer $lexer` as first parameter instead of using `$this->`
- Replace all cursor method calls with `$lexer->` calls
- Keep the evaluation logic (data resolution, `compare()`, `toBool()`, short-circuit) — that's the unique responsibility

**D) Clean up `ExpressionLanguageValidator` docblock in `ExpressionEvaluator`**

The comment `The grammar mirrors {@see ExpressionLanguageValidator} exactly` can be simplified since the shared lexer now ensures this contract.

### Files changed/created
1. **NEW** `app/Services/Expression/ExpressionLexer.php`
2. `app/Services/Verification/ExpressionLanguageValidator.php`
3. `app/Services/Execution/Expression/ExpressionEvaluator.php`

### No changes needed
- `WorkflowsServiceProvider` — both classes were already singletons, they still work fine
- `ExpressionVerificationRule` — only depends on `ExpressionLanguageValidator::validateBoolean()`, interface unchanged
- `IfNodeTypeRule` — same
- `NodeExecutionContext` — only uses `ExpressionEvaluator::evaluate()`/`evaluateBoolean()`, interface unchanged
- `TemplateInterpolator` — same
- `WorkflowRuntime` — same

---

## Migration + Testing

### Order of operations
1. Implement Issue 3 first (create `ExpressionLexer`, update both consumers) — no other code depends on this file
2. Run `composer test` to verify nothing broke
3. Implement Issue 2 (tag binding, rewrite `NodeTypeVerificationRule`, prune `WorkflowVerificationService`)
4. Run `composer test` again

### Potential risks
- **Issue 2**: Ensure the `iterable` tag resolution works with the `NodeTypeVerificationRule` constructor. Laravel's `$tagged` method resolves tagged bindings as an ordered array. The tag name `'node-type-rules'` must match in both the `->tag()` call and the `iterable` resolution (Laravel uses the type hint for `iterable`, but the tag is resolved automatically when you type-hint `iterable` in a constructor and have a tag binding — actually, this isn't quite right. For tagged bindings to auto-resolve to `iterable`, you need to use the `TaggedValue` approach or use `$this->app->when(NodeTypeVerificationRule::class)->needs(iterable::class)->giveTagged('node-type-rules')`. Let me clarify.

Actually, in Laravel, auto-resolution of tagged bindings for `iterable` type hints works like this:
- When the container resolves a class that type-hints `iterable $rules`, it looks for all tagged bindings with the tag `$rules` (the parameter name).
- But this isn't quite right either. The standard approach is:

```php
$this->app->when(NodeTypeVerificationRule::class)
    ->needs('$rules')
    ->giveTagged('node-type-rules');
```

Or more simply, you can use `$this->app->tag()` combined with constructor injection of `iterable`. Actually, let me check how Laravel handles this...

In Laravel, you can do:
```php
$this->app->tag([Class1::class, Class2::class], 'some-tag');
```

Then to inject them:
```php
class Foo {
    public function __construct(
        protected iterable $someTag
    ) {}
}
```

This works because Laravel's container resolves `iterable` parameter names by looking up tagged bindings with that parameter name. Actually wait, that's not right either. Let me think again.

Actually, Laravel's contextual binding is the way:
```php
$this->app->when(NodeTypeVerificationRule::class)
    ->needs('$rules')
    ->giveTagged('node-type-rules');
```

Or you can do it in the constructor by passing the tagged array directly. The simplest approach is:

```php
// In service provider:
$this->app->tag([...], 'node-type-rules');

// In the class:
public function __construct(iterable $rules) { ... }

// Contextual binding in service provider:
$this->app->when(NodeTypeVerificationRule::class)
    ->needs('$rules')
    ->giveTagged('node-type-rules');
```

So we need the contextual binding too. Let me add that to the plan.

Actually, I just realized there might be a simpler approach. Let me check if Laravel auto-resolves iterable parameters from tags.

Looking at the Laravel docs: When you type-hint `iterable` and the parameter name matches a tag, it will auto-resolve. Actually, I think the way it works is:

```php
// Tag them
$this->app->tag([...], 'node-type-rules');

// In constructor, parameter name doesn't matter for contextual binding
// You need explicit contextual binding:
$this->app->when(NodeTypeVerificationRule::class)
    ->needs('$rules')
    ->giveTagged('node-type-rules');
```

Yes, so I need to add the contextual binding step to the plan.

Let me also double-check - I should handle the `register()` method removal carefully. The `WorkflowVerificationService` currently calls `$this->nodeTypeRule->register(...)` in its constructor. We need to remove those calls and the corresponding injections.

Let me now also check if there are test files that directly instantiate `WorkflowVerificationService` or mock the NodeTypeRule classes.Let me check one thing about how tests reference these classes before finalizing the plan.

**Tool: grep**

**Input:**
```json
{
  "include": "*Test.php",
  "pattern": "WorkflowVerificationService|NodeTypeVerificationRule|NodeTypeRule|ExpressionLanguageValidator|ExpressionEvaluator",
  "path": "/home/huda/No-Code-Workflow-Automation-Platform"
}
```

**Output:**
```
Found 20 matches
/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/WorkflowVerificationServiceTest.php:
  Line 16: use Modules\Workflows\Services\WorkflowVerificationService;

  Line 19: class WorkflowVerificationServiceTest extends TestCase

  Line 23:     protected WorkflowVerificationService $verificationService;

  Line 30:         $this->verificationService = app(WorkflowVerificationService::class);


/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/SubWorkflowNodeTypeRuleTest.php:
  Line 16: use Modules\Workflows\Services\Verification\Rules\NodeType\SubWorkflowNodeTypeRule;

  Line 21: class SubWorkflowNodeTypeRuleTest extends TestCase

  Line 25:     protected SubWorkflowNodeTypeRule $rule;

  Line 32:         $this->rule = new SubWorkflowNodeTypeRule;


/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/Execution/TemplateInterpolatorTest.php:
  Line 5: use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;

  Line 16:         $this->interpolator = new TemplateInterpolator(new ExpressionEvaluator);


/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/Execution/ExpressionEvaluatorTest.php:
  Line 5: use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;

  Line 10: class ExpressionEvaluatorTest extends TestCase

  Line 12:     protected ExpressionEvaluator $evaluator;

  Line 17:         $this->evaluator = new ExpressionEvaluator;


/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/MergeNodeVerificationTest.php:
  Line 7: use Modules\Workflows\Services\WorkflowVerificationService;

  Line 19:     protected WorkflowVerificationService $service;

  Line 26:         $this->service = app(WorkflowVerificationService::class);


/home/huda/No-Code-Workflow-Automation-Platform/tests/Feature/WorkflowVerificationTest.php:
  Line 13: use Modules\Workflows\Services\WorkflowVerificationService;

  Line 35:     protected WorkflowVerificationService $validator;

  Line 43:         $this->validator = app(WorkflowVerificationService::class);

```

---

## Assistant (Plan · DeepSeek V4 Flash Free · 3.6s)

_Thinking:_

Now I see the test files too. Let me check a few of them to understand how they use these services:

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/Execution/ExpressionEvaluatorTest.php"
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/Execution/ExpressionEvaluatorTest.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Tests\Unit\Execution;
4: 
5: use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
6: use PHPUnit\Framework\Attributes\DataProvider;
7: use RuntimeException;
8: use Tests\TestCase;
9: 
10: class ExpressionEvaluatorTest extends TestCase
11: {
12:     protected ExpressionEvaluator $evaluator;
13: 
14:     protected function setUp(): void
15:     {
16:         parent::setUp();
17:         $this->evaluator = new ExpressionEvaluator;
18:     }
19: 
20:     /**
21:      * @param  array<string, mixed>  $data
22:      */
23:     #[DataProvider('booleanCases')]
24:     public function test_evaluate_boolean(string $expression, array $data, bool $expected): void
25:     {
26:         $this->assertSame($expected, $this->evaluator->evaluateBoolean($expression, $data));
27:     }
28: 
29:     /**
30:      * @return array<string, array{0: string, 1: array<string, mixed>, 2: bool}>
31:      */
32:     public static function booleanCases(): array
33:     {
34:         $context = fn (array $c): array => ['context' => $c];
35: 
36:         return [
37:             'numeric gt true' => ['context.age > 18', $context(['age' => 21]), true],
38:             'numeric gt false' => ['context.age > 18', $context(['age' => 16]), false],
39:             'numeric gte boundary' => ['context.age >= 18', $context(['age' => 18]), true],
40:             'string eq' => ["context.status == 'active'", $context(['status' => 'active']), true],
41:             'string neq' => ["context.status != 'active'", $context(['status' => 'paused']), true],
42:             'and both true' => ['context.age > 18 && context.vip', $context(['age' => 40, 'vip' => true]), true],
43:             'and short circuit false' => ['context.age > 18 && context.missing > 5', $context(['age' => 40]), false],
44:             'or first true' => ['context.vip || context.age > 90', $context(['vip' => true, 'age' => 10]), true],
45:             'or both false' => ['context.vip || context.age > 90', $context(['vip' => false, 'age' => 10]), false],
46:             'negation' => ['!context.blocked', $context(['blocked' => false]), true],
47:             'parentheses precedence' => ['(context.a || context.b) && context.c', $context(['a' => true, 'b' => false, 'c' => true]), true],
48:             'missing identifier is null' => ['context.missing > 5', $context([]), false],
49:             'numeric string coercion' => ['context.amount == 100', $context(['amount' => '100']), true],
50:             'boolean literal' => ['true && !false', [], true],
51:             'nested path' => ['context.user.role == "manager"', $context(['user' => ['role' => 'manager']]), true],
52:         ];
53:     }
54: 
55:     public function test_evaluate_returns_raw_values(): void
56:     {
57:         $this->assertSame(42, $this->evaluator->evaluate('context.n', ['context' => ['n' => 42]]));
58:         $this->assertSame('hi', $this->evaluator->evaluate("'hi'"));
59:         $this->assertNull($this->evaluator->evaluate('context.nope', ['context' => []]));
60:     }
61: 
62:     public function test_empty_expression_throws(): void
63:     {
64:         $this->expectException(RuntimeException::class);
65:         $this->evaluator->evaluate('   ');
66:     }
67: 
68:     public function test_unbalanced_parenthesis_throws(): void
69:     {
70:         $this->expectException(RuntimeException::class);
71:         $this->evaluator->evaluateBoolean('(context.a == 1', ['context' => ['a' => 1]]);
72:     }
73: }

(End of file - total 73 lines)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/Execution/TemplateInterpolatorTest.php",
  "limit": 20
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/Execution/TemplateInterpolatorTest.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Tests\Unit\Execution;
4: 
5: use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
6: use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
7: use Tests\TestCase;
8: 
9: class TemplateInterpolatorTest extends TestCase
10: {
11:     protected TemplateInterpolator $interpolator;
12: 
13:     protected function setUp(): void
14:     {
15:         parent::setUp();
16:         $this->interpolator = new TemplateInterpolator(new ExpressionEvaluator);
17:     }
18: 
19:     public function test_renders_placeholder(): void
20:     {

(Showing lines 1-20 of 45. Use offset=21 to continue.)
</content>
```

**Tool: read**

**Input:**
```json
{
  "filePath": "/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/WorkflowVerificationServiceTest.php",
  "limit": 40
}
```

**Output:**
```
<path>/home/huda/No-Code-Workflow-Automation-Platform/tests/Unit/WorkflowVerificationServiceTest.php</path>
<type>file</type>
<content>
1: <?php
2: 
3: namespace Tests\Unit;
4: 
5: use Illuminate\Foundation\Testing\RefreshDatabase;
6: use Illuminate\Support\Facades\Hash;
7: use Modules\Auth\Enums\BusinessType;
8: use Modules\Auth\Enums\Role;
9: use Modules\Auth\Models\Tenant;
10: use Modules\Auth\Models\User;
11: use Modules\Team\Models\Team;
12: use Modules\Team\Models\TeamMembership;
13: use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
14: use Modules\Workflows\Enums\WorkflowStatus;
15: use Modules\Workflows\Models\Workflow;
16: use Modules\Workflows\Services\WorkflowVerificationService;
17: use Tests\TestCase;
18: 
19: class WorkflowVerificationServiceTest extends TestCase
20: {
21:     use RefreshDatabase;
22: 
23:     protected WorkflowVerificationService $verificationService;
24: 
25:     protected function setUp(): void
26:     {
27:         parent::setUp();
28: 
29:         $this->seed(NodeDefinitionSeeder::class);
30:         $this->verificationService = app(WorkflowVerificationService::class);
31:     }
32: 
33:     public function test_unknown_trigger_and_node_types_are_rejected(): void
34:     {
35:         $definition = $this->validDefinition();
36:         $definition['trigger']['type'] = 'mystery-trigger';
37:         $definition['nodes'][0]['type'] = 'mystery-node';
38: 
39:         $result = $this->verify($definition);
40: 

(Showing lines 1-40 of 310. Use offset=41 to continue.)
</content>
```

---

## Assistant (Plan · DeepSeek V4 Flash Free · 15.0s)

_Thinking:_

Good, I now have all the information I need. The tests use `app(WorkflowVerificationService::class)` which resolves from the container, so they'll automatically benefit from the tag-based injection. And the `ExpressionEvaluator` tests use `new ExpressionEvaluator()` directly — the constructor doesn't change since the lexer is created internally per call.

Let me now write the comprehensive implementation plan.

Perfect. All existing tests use `app()` or `new ExpressionEvaluator()` — neither needs modification since both changes are internal to existing constructors/methods.

Here's the final implementation plan:

---

## Issue 2 — Tag-based NodeTypeRule injection

### 1. `WorkflowsServiceProvider::register()` — add tag binding

Add `use` imports for all 10 node-type rule classes, then after the existing singleton registrations:

```php
$this->app->tag([
    IfNodeTypeRule::class,
    ForkNodeTypeRule::class,
    SwitchNodeTypeRule::class,
    MergeNodeTypeRule::class,
    TaskNodeTypeRule::class,
    SendEmailNodeTypeRule::class,
    AiGeneratorNodeTypeRule::class,
    TerminationNodeTypeRule::class,
    SubWorkflowNodeTypeRule::class,
    DynamicEntryNodeTypeRule::class,
], 'node-type-rules');

$this->app->when(NodeTypeVerificationRule::class)
    ->needs('$rules')
    ->giveTagged('node-type-rules');
```

### 2. `NodeTypeVerificationRule.php`

Replace the current class with:

```php
class NodeTypeVerificationRule implements VerificationRule
{
    /** @var array<string, NodeTypeRule> */
    protected array $rules = [];

    public function __construct(iterable $rules)
    {
        foreach ($rules as $rule) {
            $this->rules[$rule->nodeType()] = $rule;
        }
    }

    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
        VerificationMode $mode = VerificationMode::Full,
    ): void {
        foreach ($graph->nodes() as $index => $node) {
            $type = $node['type'] ?? '';
            if (isset($this->rules[$type])) {
                $this->rules[$type]->verify($node, (int) $index, $graph, $result, $workflow);
            }
        }
    }
}
```

Remove the `register()` method — it's no longer needed.

### 3. `WorkflowVerificationService.php` — prune constructor

Remove these 10 constructor parameters:
- `IfNodeTypeRule $ifNodeTypeRule`
- `ForkNodeTypeRule $forkNodeTypeRule`  
- `SwitchNodeTypeRule $switchNodeTypeRule`
- `MergeNodeTypeRule $mergeNodeTypeRule`
- `TaskNodeTypeRule $taskNodeTypeRule`
- `SendEmailNodeTypeRule $sendEmailNodeTypeRule`
- `AiGeneratorNodeTypeRule $aiGeneratorNodeTypeRule`
- `TerminationNodeTypeRule $terminationNodeTypeRule`
- `SubWorkflowNodeTypeRule $subWorkflowNodeTypeRule`
- `DynamicEntryNodeTypeRule $dynamicEntryNodeTypeRule`

Remove the entire constructor body (the 10 `$this->nodeTypeRule->register()` calls).

Remove the corresponding 10 `use` imports.

---

## Issue 3 — Shared Expression Lexer

### 1. Create `Services/Expression/ExpressionLexer.php`

Namespace: `Modules\Workflows\Services\Expression`

```php
<?php

namespace Modules\Workflows\Services\Expression;

class ExpressionLexer
{
    /** @var array<int, array{type: string, value: string}> */
    private array $tokens;

    private int $position = 0;

    public function __construct(string $expression)
    {
        $this->tokens = $this->tokenize($expression);
    }

    public function consumeOperator(string $operator): bool
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token !== null && $token['type'] === 'operator' && $token['value'] === $operator) {
            $this->position++;
            return true;
        }
        return false;
    }

    public function consumeAnyOperator(array $operators): ?string
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token !== null && $token['type'] === 'operator' && in_array($token['value'], $operators, true)) {
            $this->position++;
            return $token['value'];
        }
        return null;
    }

    public function next(): ?array
    {
        $token = $this->tokens[$this->position] ?? null;
        if ($token !== null) {
            $this->position++;
        }
        return $token;
    }

    public function atEnd(): bool
    {
        return $this->position >= count($this->tokens);
    }

    public function currentValue(): string
    {
        return (string) ($this->tokens[$this->position]['value'] ?? 'end of expression');
    }

    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $expression): array
    {
        // Exact same tokenize() code from both classes
        $tokens = [];
        $length = strlen($expression);
        $offset = 0;

        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $expression, $match, 0, $offset)) {
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/\G(&&|\|\||==|!=|>=|<=|>|<|!|\(|\))/A', $expression, $match, 0, $offset)) {
                $tokens[] = ['type' => 'operator', 'value' => $match[1]];
                $offset += strlen($match[1]);
                continue;
            }

            if (preg_match('/\G"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/A', $expression, $match, 0, $offset)) {
                $tokens[] = ['type' => 'string', 'value' => $match[0]];
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?/A', $expression, $match, 0, $offset)) {
                $tokens[] = ['type' => 'number', 'value' => $match[0]];
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/\G[A-Za-z_][A-Za-z0-9_.]*/A', $expression, $match, 0, $offset)) {
                $value = $match[0];
                $tokens[] = [
                    'type' => in_array(strtolower($value), ['true', 'false', 'null'], true) ? 'literal' : 'identifier',
                    'value' => $value,
                ];
                $offset += strlen($value);
                continue;
            }

            throw new \RuntimeException('Invalid expression token near "'.substr($expression, $offset, 12).'".');
        }

        return $tokens;
    }
}
```

### 2. Update `ExpressionLanguageValidator.php`

- Remove properties: `$tokens`, `$position`, `$variables` (keep `$variables` as a local accumulator)
- `validateBoolean()`: create `$lexer = new ExpressionLexer($expression)` and pass it to parse methods
- All parse methods take `ExpressionLexer $lexer` as first parameter
- `$this->variables` becomes a local variable tracked via a private method or stored locally — simplest: keep `$this->variables` but reset it in `validateBoolean()` (the reentrancy fix was a separate issue)
- Replace `$this->consumeOperator(...)` → `$lexer->consumeOperator(...)`
- Replace `$this->consumeAnyOperator(...)` → `$lexer->consumeAnyOperator(...)`
- Replace `$this->next()` → `$lexer->next()`
- Replace `$this->atEnd()` → `$lexer->atEnd()`
- Replace `$this->currentValue()` → `$lexer->currentValue()`
- Remove `tokenize()`, `consumeOperator()`, `consumeAnyOperator()`, `next()`, `atEnd()`, `currentValue()` methods

### 3. Update `ExpressionEvaluator.php`

Same pattern:
- Remove `$tokens`, `$position` properties
- `evaluate()`: create `$lexer = new ExpressionLexer($expression)` at the start
- All parse methods take `ExpressionLexer $lexer` instead of `$this->`
- Short-circuit methods `skipComparison()` / `skipAnd()` take `$lexer` too
- Replace all cursor calls with `$lexer->` calls
- Remove `tokenize()`, `consumeOperator()`, `consumeAnyOperator()`, `next()`, `atEnd()`, `currentValue()` methods

### Test changes needed

| Test | Change |
|---|---|
| `ExpressionEvaluatorTest` line 17 | `new ExpressionEvaluator` — **no change** (constructor stays parameterless) |
| `TemplateInterpolatorTest` line 16 | `new TemplateInterpolator(new ExpressionEvaluator)` — **no change** |
| `WorkflowVerificationServiceTest` line 30 | `app(WorkflowVerificationService::class)` — **no change** |
| `SubWorkflowNodeTypeRuleTest` line 32 | `new SubWorkflowNodeTypeRule` — **no change** |
| `MergeNodeVerificationTest` line 26 | `app(WorkflowVerificationService::class)` — **no change** |
| `WorkflowVerificationTest` line 43 | `app(WorkflowVerificationService::class)` — **no change** |

Zero test changes needed.

### Verification

```bash
composer test
composer run format  # Laravel Pint
```

---

Want me to proceed with implementation?

---

