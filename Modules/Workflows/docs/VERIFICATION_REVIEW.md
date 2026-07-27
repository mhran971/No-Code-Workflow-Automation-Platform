# Verification System Review

> Generated review of `Modules/Workflows/app/Services/Verification/`
> Scope: 32 PHP files, ~3000 lines across the verification subsystem.

---

## Architecture Overview

```
WorkflowVerificationService (orchestrator)
  ├── WorkflowDefinitionNormalizer        — normalizes raw input to canonical shape
  ├── WorkflowDefinitionGraph              — adjacency-list graph with topo sort, cycle detection
  ├── 8 top-level VerificationRule implementations (run sequentially)
  │     ├── SyntaxVerificationRule         — shape, types, config field validation
  │     ├── GraphControlFlowVerificationRule — reachability, degrees, cycles
  │     ├── StructuredControlFlowVerificationRule — split/merge pairing via graph reduction
  │     ├── ExpressionVerificationRule     — boolean expression syntax on edges/nodes
  │     ├── ContextualVerificationRule     — assignee/KB doc existence (requires $workflow)
  │     ├── NodeTypeVerificationRule       — dispatcher for per-node-type rules
  │     ├── DataFlowVerificationRule       — variable availability, parallel-write conflicts
  │     └── FormTriggerVerificationRule    — form field structure, access level
  └── NodeTypeVerificationRule dispatches to 11 NodeTypeRule implementations
        ├── IfNodeTypeRule
        ├── ForkNodeTypeRule
        ├── SwitchNodeTypeRule
        ├── MergeNodeTypeRule
        ├── TaskNodeTypeRule
        ├── SendEmailNodeTypeRule
        ├── TerminationNodeTypeRule
        ├── SubWorkflowNodeTypeRule
        ├── DynamicEntryNodeTypeRule
        ├── AiGeneratorNodeTypeRule        ⚠️ NOT REGISTERED (dead code)
        └── (no rule for dynamic-flow, manual-trigger, form-trigger, webhook-trigger)

Supporting services:
  ├── ExpressionLanguageValidator          — recursive-descent parser for boolean expressions
  ├── ControlFlowReducer                  — academic graph reduction for soundness checking
  ├── DataFlowAnalyzer                    — forward data-flow analysis (guaranteed/possible sets)
  └── VariableAvailability trait           — shared context-variable checking for node-type rules
```

---

## 1. Logic Mismatch Issues

### 1A. ~~Contradictory rules for `dynamic-entry` nodes~~ ✅ FIXED

**Files:** `GraphControlFlowVerificationRule.php`, `DynamicEntryNodeTypeRule.php`

Both trigger and dynamic-entry nodes are now validated at the graph level in `verifyNodeDegrees` with a single `graph.entry_has_incoming` check. The incoming-edges check was removed from `DynamicEntryNodeTypeRule`.

---

### 1B. `edgeCanBelongToExplicitLoop` references a nonexistent `loop` node type

**File:** `WorkflowDefinitionGraph.php:329-331`

```php
protected function edgeCanBelongToExplicitLoop(string $sourceNodeId, string $targetNodeId): bool
{
    return $this->nodeType($sourceNodeId) === 'loop' || $this->nodeType($targetNodeId) === 'loop';
}
```

There is no `loop` node type in the seed data, CLAUDE.md, or anywhere in the codebase. This code path is dead. Every cycle edge is always flagged as invalid, which may be correct for the current node catalog, but the dead code suggests an incomplete feature or stale abstraction.

**Fix:** Either remove the dead code or implement the `loop` node type if loops are planned.

---

### 1C. `branch_type` is called "legacy" but is still the gating signal

**Files:** `WorkflowDefinitionNormalizer.php:99`, `ExpressionVerificationRule.php:33`

Normalizer comment at line 99:
```php
// 'branch_type' is legacy and ignored; derive semantics from edge properties instead
```

But `ExpressionVerificationRule::verifyEdgeExpressions` (line 33) reads it directly:
```php
if (($edge['branch_type'] ?? 'default') !== 'conditional' || (bool) ($edge['is_default_branch'] ?? false)) {
    continue;
}
```

The comment contradicts the code. Either `branch_type` is still the source of truth (fix the comment) or the expression rule should use a derived signal (fix the logic).

---

### 1D. `MergeNodeTypeRule` only targets `merge` — misses `merge-and`/`merge-or`

**Files:** `MergeNodeTypeRule.php:15`, `WorkflowsServiceProvider.php:160-161`

The execution engine registers `merge-and` and `merge-or` as separate types. But `MergeNodeTypeRule::nodeType()` returns `'merge'` only. If a canvas definition uses `merge-and` or `merge-or` as a node type, `MergeNodeTypeRule` never validates it — no mode check, no branch count check.

---

### 1E. `ControlFlowReducer::classify` has the same gap

**File:** `ControlFlowReducer.php:107-115`

Only classifies nodes with type `'merge'`. A node typed `merge-and` or `merge-or` falls through to `'plain'`, skipping soundness checking entirely.

---

## 2. Code Smells

### 2A. DB queries inside verification rules (N+1 risk)

| Rule | Query | Frequency |
|------|-------|-----------|
| `SyntaxVerificationRule` | `Node::query()->where('is_active', true)->with('configFields')->get()` | Every validation call |
| `ContextualVerificationRule` | `User::query()->where(...)` + `TeamMembership::query()->where(...)` | Per task-node |
| `ContextualVerificationRule` | `Document::query()->where(...)` | Per KB doc per AI node |
| `SubWorkflowNodeTypeRule` | `Workflow::find()` | Per sub-workflow node |

None of these are cached or batched.

---

### 2B. `ExpressionLanguageValidator` singleton with mutable state

**File:** `ExpressionLanguageValidator.php:12-13`

Registered as singleton (`WorkflowsServiceProvider.php:93`) but stores `$this->lexer` and `$this->variables` as instance properties overwritten on each `validateBoolean()` call. Latent bug in any async context; code smell even in sync PHP-FPM.

---

### 2C. `hasParallelPaths` is O(N * (V+E)) per node

**File:** `Concerns/VariableAvailability.php:147-176`

For every node using the trait (if, switch, ai-generator, sub-workflow, send-email), it calls `reachableFrom()` for each outgoing edge of each ancestor. No memoization of reachability results.

---

### 2D. `WorkflowDefinitionGraph` doesn't memoize graph traversals

`reachableFrom()`, `nodesThatCanReachAny()`, `ancestorNodeIds()`, `topologicalOrder()` are all called multiple times across different rules and the trait. Each call rebuilds the traversal from scratch.

---

### 2E. Inconsistent error code naming convention

| Pattern | Examples |
|---------|----------|
| Dots | `definition.nodes_invalid`, `trigger.missing` |
| Underscores | `if_node.expression_missing`, `ai_generator.prompt_missing` |
| Mixed | `graph.trigger_missing` vs `graph.unstructured_cycle` |

No documented convention.

---

### 2F. Duplicated form-field validation logic

`FormTriggerVerificationRule` and `TaskNodeTypeRule` have identical constants (`VALID_FIELD_TYPES`, `OPTIONS_REQUIRED_TYPES`) and nearly identical field validation loops (~30 lines each). Should be extracted into a shared concern or service.

---

## 3. Over-Engineering Concerns

### 3A. `DataFlowAnalyzer` — unused code paths

315 lines of forward data-flow analysis with producer tracking, guaranteed/possible sets, and conflict detection. Sophisticated and well-implemented, but:

- `reportMergeOutputAvailability` checks `outputVariables` on merge nodes, but no node type in the current system actually declares output variables on merge config. This code path may never execute.
- The `producedBy` tracking with `overlayOwnWrites` and `combineProducedBy` adds significant complexity. The simpler conflict detection (check if two predecessor branches each have an independent writer) might suffice.

---

### 3B. `ControlFlowReducer` — academic graph reduction algorithm

Well-implemented classic workflow-net reduction (sequence rule + block rule to fixpoint). The question is whether the current node topology (if → merge, fork → merge, switch → merge) warrants this level of rigor. A simpler approach could check: "each split has a matching merge, each merge has a matching split, modes are compatible."

---

### 3C. `ExpressionLanguageValidator` — full parser with type inference

Or/and/comparison/unary/primary precedence levels, type tracking. The only caller checks `$validation->valid` and `$validation->variables`. The type inference is only used to reject bare variable references — a simpler regex-based approach might suffice.

---

## 4. Under-Engineering Gaps

### 4A. `AiGeneratorNodeTypeRule` is never executed

**File:** `WorkflowsServiceProvider.php:122-132`

The `node-type-rules` tag includes 9 rules but `AiGeneratorNodeTypeRule` is missing. The class exists (53 lines, validates prompt + outputVariable), but is dead code. AI generator nodes get zero type-specific validation.

**Fix:** Add `AiGeneratorNodeTypeRule::class` to the tagged array.

---

### 4B. `dynamic-flow` has no node-type rule

The execution engine has `DynamicFlowExecutor`, but no `DynamicFlowNodeTypeRule`. Dynamic flow nodes get no config validation.

---

### 4C. `VerificationMode::Segment` support is inconsistent

| Rule | Checks mode? |
|------|-------------|
| SyntaxVerificationRule | Yes — skips trigger validation for Segment |
| GraphControlFlowVerificationRule | Yes — has segment-specific reachability |
| StructuredControlFlowVerificationRule | No |
| ExpressionVerificationRule | No |
| ContextualVerificationRule | No |
| NodeTypeVerificationRule | No |
| DataFlowVerificationRule | No |
| FormTriggerVerificationRule | No |

Rules that validate trigger-related concerns don't skip for Segment mode, running checks that may be irrelevant or produce false positives.

---

### 4D. No test coverage

No test files found for the verification system. The 32-file, 3000+ line subsystem has no automated tests.

---

### 4E. No validation of `settings` contents

Normalizer copies `settings` as-is; syntax rule checks it's an array. No rule validates any settings values. If settings control execution behavior (timeouts, error handling, etc.), invalid settings silently pass validation.

---

### 4F. `ContextualVerificationRule` silently skips when `$workflow` is null

Publish-time validation (`WorkflowController::validate`) calls `->verify($definition)` without a workflow model. This means assignee validity and KB doc existence are never checked during pre-publish validation. Users won't see these errors until publish time.

---

## 5. Refactoring Plan

### Phase 1: Quick Wins (high impact, low risk)

| # | Task | Files | Est. |
|---|------|-------|------|
| 1.1 | Register `AiGeneratorNodeTypeRule` in service provider tag | `WorkflowsServiceProvider.php` | 5 min |
| 1.2 | ~~Fix `dynamic-entry` contradiction — exclude from graph degree check~~ ✅ | `GraphControlFlowVerificationRule.php`, `DynamicEntryNodeTypeRule.php` | Done |
| 1.3 | Cache `Node::query()` in `SyntaxVerificationRule` | `SyntaxVerificationRule.php` | 15 min |
| 1.4 | Extract duplicated field validation into shared concern | New `Concerns/FormFieldValidation.php`, `FormTriggerVerificationRule.php`, `TaskNodeTypeRule.php` | 30 min |
| 1.5 | Fix `branch_type` comment or fix `ExpressionVerificationRule` | `WorkflowDefinitionNormalizer.php` or `ExpressionVerificationRule.php` | 10 min |
| 1.6 | Handle `merge-and`/`merge-or` in `MergeNodeTypeRule` and `ControlFlowReducer` | `MergeNodeTypeRule.php`, `ControlFlowReducer.php` | 20 min |

### Phase 2: Structural Improvements (medium impact)

| # | Task | Files | Est. |
|---|------|-------|------|
| 2.1 | Add `skipForSegment()` to `VerificationRule` interface | `VerificationRule.php`, all implementations | 45 min |
| 2.2 | Add graph traversal memoization to `WorkflowDefinitionGraph` | `WorkflowDefinitionGraph.php` | 30 min |
| 2.3 | Replace singleton `ExpressionLanguageValidator` with non-singleton or pure function | `ExpressionLanguageValidator.php`, `WorkflowsServiceProvider.php` | 20 min |
| 2.4 | Batch DB queries in `ContextualVerificationRule` | `ContextualVerificationRule.php` | 30 min |
| 2.5 | Standardize error code naming convention (pick one, document it) | All rule files | 30 min |
| 2.6 | Add `NodeTypeRule` for `dynamic-flow` | New `DynamicFlowNodeTypeRule.php`, `WorkflowsServiceProvider.php` | 20 min |

### Phase 3: Deeper Refactoring (if warranted)

| # | Task | Files | Est. |
|---|------|-------|------|
| 3.1 | Evaluate `DataFlowAnalyzer` complexity — simplify if `outputVariables` on merge is unused | `DataFlowAnalyzer.php`, `DataFlowVerificationRule.php` | 1 hr |
| 3.2 | Consider splitting `VerificationRule` into `SyntaxRule`/`GraphRule`/`SemanticRule` sub-interfaces | `VerificationRule.php`, all implementations | 2 hr |
| 3.3 | Add verification integration tests (known-good + known-bad workflow definitions) | New test files | 4 hr |
| 3.4 | Remove dead `edgeCanBelongToExplicitLoop` code or implement `loop` node type | `WorkflowDefinitionGraph.php` | 30 min |
| 3.5 | Add `settings` validation rule | New rule or extend `SyntaxVerificationRule` | 30 min |

---

## File Reference

| File | Lines | Role |
|------|-------|------|
| `WorkflowVerificationService.php` | 60 | Orchestrator |
| `WorkflowDefinitionNormalizer.php` | 113 | Input normalization |
| `WorkflowDefinitionGraph.php` | 333 | Graph abstraction |
| `ExpressionLanguageValidator.php` | 143 | Expression parser |
| `ControlFlowReducer.php` | 286 | Split/merge soundness |
| `DataFlowAnalyzer.php` | 315 | Variable availability analysis |
| `Rules/SyntaxVerificationRule.php` | 271 | Shape + type validation |
| `Rules/GraphControlFlowVerificationRule.php` | 173 | Reachability + degrees |
| `Rules/StructuredControlFlowVerificationRule.php` | 97 | Split/merge pairing |
| `Rules/ExpressionVerificationRule.php` | 85 | Expression syntax |
| `Rules/ContextualVerificationRule.php` | 117 | Assignee + KB validation |
| `Rules/NodeTypeVerificationRule.php` | 40 | Node-type dispatcher |
| `Rules/DataFlowVerificationRule.php` | 204 | Variable conflict detection |
| `Rules/FormTriggerVerificationRule.php` | 103 | Form trigger structure |
| `Rules/NodeType/*.php` | 11 files | Per-node-type validation |
| `Rules/NodeType/Concerns/VariableAvailability.php` | 177 | Shared variable checking |
| `Data/WorkflowVerificationResult.php` | 104 | Result accumulator |
| `Data/WorkflowViolation.php` | 47 | Single violation value object |
| `Data/ControlFlowReductionResult.php` | 22 | Reduction outcome |
| `Data/DataFlowResult.php` | 35 | Data-flow outcome |
| `Data/ExpressionValidationResult.php` | 16 | Expression outcome |
