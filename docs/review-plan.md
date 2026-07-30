# Workflows Module Review & Refactor Plan

## Overview

Comprehensive code review of `Modules/Workflows/` covering four components:
Verification, Execution, Management, and Trigger systems.

---

## 1. Verification Pipeline

### Overengineered

- **8-stage pipeline is excessive** — `GraphControlFlowVerificationRule` and
  `StructuredControlFlowVerificationRule` overlap. The graph already validates
  reachability, cycles, entry/terminal nodes, and node degree. The
  `ControlFlowReducer` (284 lines) applies SESE reduction rules (sequence +
  block) for deadlock/lack-of-synchronization detection. This is
  compiler-theory rigor for a drag-and-drop automation tool. Merge/split
  pairing can be validated with ~50 lines of simpler logic.

- **`DataFlowAnalyzer` (313 lines)** — Forward data-flow analysis computing
  guaranteed/possible variable sets with parallel-merge conflict detection.
  This is a full compiler data-flow pass. Achievable with straightforward
  reachability checks in ~80 lines.

- **`WorkflowDefinitionGraph` (333 lines)** — Handles topological ordering,
  cycle detection, reachability, ancestor analysis, entry/terminal nodes. Some
  logic duplicates the reducer. Conflates being a graph data structure AND
  doing complex analysis.

- **Duplicated expression parser** — `ExpressionLanguageValidator` (142 lines)
  + `ExpressionLexer` (120 lines) for validation and `ExpressionEvaluator`
  (252 lines) + `TemplateInterpolator` (69 lines) for runtime execution. The
  grammar is implemented twice — violates DRY.

- **`WorkflowDefinitionNormalizer` (113 lines)** with `_raw`, `_invalid`,
  `_index` metadata — storing raw input inside normalized output defeats
  normalization's purpose. `_raw` leaks raw format to every downstream
  consumer.

### Underengineered

- **No caching for `Node`/`NodeConfigField` lookups** — `SyntaxVerificationRule`
  queries `Node::with('configFields')->get()` on every validation call.
- **`VerificationMode::Segment`** — Only used by `DynamicFlowController`, but
  mode-checking is scattered across rules via `if ($mode === ...)`.
- **Empty `Modules/Workflows/tests/`** — All tests in top-level `tests/`.
- **No verification benchmark tests** — Data-flow and control-flow analyses are
  computationally heavy.

### Code Smells

- 9 dependencies injected into `WorkflowVerificationService` constructor
- `WorkflowVerificationService::rules()` is a hardcoded array (should use
  tagged bindings)
- `_raw` anti-pattern leaks raw format into normalized data

---

## 2. Execution Engine

### Overengineered

- **`WorkflowRuntime` (675 lines)** — God class handling 8+ responsibilities:
  advance loop, 5 outcome handlers, merge synchronization, cancel,
  retry-from-node, retry scheduling, instance completion/failure,
  parent-execution wake-up, plan resolution, broadcasting.
- **14 executor classes** — Many trivially small: `TerminationNodeExecutor` (26
  lines), `WebhookTriggerExecutor` (29 lines), `FormTriggerExecutor` (35
  lines). Several could be closures.
- **`ExecutionPlan` (200 lines) + `ExecutionPlanCompiler` (38 lines)** — Cache
  is per-request, effectively useless on subsequent requests.
- **`NodeExecutionContext` (121 lines)** — Buffered context mutations with
  deferred save. Unnecessary complexity for most nodes.

### Underengineered

- **No execution engine tests** — `WorkflowRuntime`, `WorkflowDispatcher`, all
  14 executors have no meaningful test coverage.
- **Merge coordination is in `WorkflowRuntime`** — `arriveAtMerge()` (57 lines)
  should be a `MergeCoordinator` service.
- **`ExecuteNodeJob` is a thin dispatcher** — Only calls `advance()` or
  `handleAdvanceFailure()`.
- **Hardcoded queue names** — "workflow-control" and "workflow-actions" strings
  duplicated across `WorkflowRuntime::queueFor()` and
  `WorkflowTaskController::resumeExecution()`.

### Code Smells

- Raw DB queries (`WorkflowNodeExecution::query()->lockForUpdate()`) alongside
  high-level operations
- `WorkflowInstance` status changes scattered across 5+ methods — no single
  state machine
- `wakeParentExecution()` checks two paths (sub-workflow vs dynamic-flow) —
  should use events
- `isRunnable()` on `WorkflowNodeExecution` only used in `claim()` — dead code

---

## 3. Management Service

### Overengineered

- **`WorkflowVersioningService` (257 lines)** — Version detection uses
  fingerprinting (normalized MD5 hash) to classify changes as structural,
  config, or none. Semver label generation (`vX.Y.Z`). Most workflows just
  need "save current draft as next version."

### Underengineered

- **No Policy classes** — `WorkflowAuthorizationService` (90 lines) duplicates
  Laravel's built-in `Gate`/`Policy` system.
- **No Service interfaces** — Hard to swap implementations for testing.
- **Soft delete doesn't check active instances** — `softDelete()` never
  verifies `active_instances > 0`.

### Code Smells

- Duplicate auth checks: `assertSameTenant()` + `canView()` called redundantly
- `$this->actor()` duplicated in 6 controllers — should be a base controller
  method or `CurrentUser` action injection

---

## 4. Trigger System

### Overengineered

- **`WorkflowTriggeringService extends BaseService`** — `BaseService`
  (`App\Services\BaseService`) is never used. Empty inheritance.

### Underengineered

- **`WorkflowTriggeringService` (42 lines)** — Too thin to justify existing.
  `triggerWebhook()` and `triggerManual()` are nearly identical (same auth,
  same status check, same dispatch).
- **`PublicFormService` (158 lines)** — Mixed responsibilities: finding forms,
  generating schemas, validating, submitting. Validation should be a
  FormRequest.
- **No idempotency for public form submission** — Re-submission creates a new
  instance each time.

### Code Smells

- `WorkflowTriggerController` uses inline `abort(403)` instead of
  `Gate::authorize()`
- `triggerManual()` accepts `$request->all()` without filtering
- `PublicFormService::submit()` dispatches without using the result

---

## 5. General Issues Summary

| Issue | Severity |
|---|---|
| `WorkflowRuntime` god class (675 lines) | High |
| Duplicated expression parser (validator + evaluator) | High |
| No execution engine tests | High |
| Missing Policy classes for authorization | Medium |
| ControlFlowReducer overengineering | Medium |
| DataFlowAnalyzer overengineering | Medium |
| 9 dependencies injected into WorkflowVerificationService | Medium |
| `_raw` normalization anti-pattern | Low |
| Hardcoded queue names | Low |
| `$this->actor()` duplicated across 6 controllers | Low |
| `BaseService` extends from `app/` not module | Low |
| Empty module tests directory | Low |
| No caching for Node definitions | Medium |
| No service contracts/interfaces | Medium |

---

## 6. Refactor Plan

### Phase 1 — High Impact (Execution Engine)
1. **Extract `MergeCoordinator`** — new service for merge synchronization
2. **Extract `InstanceStateManager`** — handle all `WorkflowInstance` status
   transitions as a state machine
3. **Extract `ChildInstanceManager`** — `wakeParentExecution()` moves here
4. **Merge `triggerWebhook()` and `triggerManual()`** — single `trigger()` method
5. **Add execution engine tests** — start with `MergeCoordinator`, then executors

### Phase 2 — Verification Consolidation
1. **Merge `GraphControlFlowVerificationRule` +
   `StructuredControlFlowVerificationRule`** into one
2. **Replace `ControlFlowReducer` SESE algorithm** with simpler
   split-count vs merge-count check (~80 lines)
3. **Replace `DataFlowAnalyzer`** with reachability-based variable check
   (~80 lines)
4. **Deduplicate expression grammar** — validator consumes same parser as
   evaluator
5. **Remove `_raw`** from normalized definitions

### Phase 3 — Laravel Best Practices
1. **Convert `WorkflowAuthorizationService` to Laravel Policies**
   (WorkflowPolicy, WorkflowInstancePolicy, WorkflowTaskPolicy)
2. **Extract base controller** with `actor()` helper
3. **Add interfaces for major services**
4. **Use tagged bindings** for verification rules
5. **Cache `Node` definitions** via `Cache::rememberForever()`
6. **Move form validation** in `PublicFormService` to a FormRequest
