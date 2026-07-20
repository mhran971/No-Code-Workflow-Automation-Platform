# Workflows Module — Class Diagram

```mermaid
classDiagram
    direction TB

    %% ============================================================
    %% MODELS
    %% ============================================================
    namespace Models {
        class Workflow {
            +int id
            +string name
            +string description
            +WorkflowStatus status
            +array draft_definition
            +int draft_revision
            +int current_version_number
            +string current_version_label
            +int total_runs
            +int active_instances
            +string public_token
            +BelongsTo tenant
            +BelongsTo team
            +BelongsTo createdBy
            +BelongsTo template
            +BelongsTo currentVersion
            +HasMany versions
            +HasMany instances
        }

        class WorkflowVersion {
            +int id
            +int workflow_id
            +int version_number
            +string version_label
            +array definition
            +string release_note
            +datetime published_at
            +BelongsTo workflow
            +BelongsTo publishedBy
        }

        class WorkflowInstance {
            +int id
            +int workflow_id
            +int workflow_version_id
            +int parent_instance_id
            +bool is_dynamic
            +WorkflowInstanceStatus status
            +TriggerType trigger_type
            +string correlation_id
            +array payload
            +array context
            +array error
            +string paused_reason
            +datetime started_at
            +datetime finished_at
            +BelongsTo workflow
            +BelongsTo workflowVersion
            +BelongsTo parent
            +HasMany nodeExecutions
            +HasMany tasks
            +HasMany events
            +isTerminal() bool
        }

        class WorkflowNode {
            +int id
            +int workflow_id
            +string node_id
            +string key
            +string label
            +float position_x
            +float position_y
            +array config
            +BelongsTo workflow
            +BelongsTo nodeType
            +BelongsTo version
            +HasMany outgoingEdges
            +HasMany incomingEdges
        }

        class WorkflowEdge {
            +int id
            +int workflow_id
            +int version_id
            +string source_node_key
            +string source_handle
            +string target_node_key
            +string target_handle
            +EdgeBranchType branch_type
            +string condition_expression
            +bool is_default_branch
            +string parallel_group_key
            +string join_node_key
            +int sort_order
            +isConditional() bool
            +isParallel() bool
        }

        class WorkflowNodeExecution {
            +int id
            +int instance_id
            +string node_key
            +string node_type
            +NodeExecutionStatus status
            +int attempt
            +string idempotency_key
            +array input
            +array output
            +array error
            +WaitType wait_type
            +datetime wait_until
            +BelongsTo instance
            +BelongsTo parent
            +HasMany children
            +HasOne task
            +isRunnable() bool
        }

        class WorkflowTask {
            +int id
            +int instance_id
            +int execution_id
            +string node_key
            +int assignee_id
            +string title
            +string description
            +array input_schema
            +string status
            +datetime due_at
            +array response
            +array draft_response
            +BelongsTo instance
            +BelongsTo execution
            +BelongsTo assignee
            +BelongsTo completedBy
            +isExpired() bool
            +displayStatus() string
        }

        class WorkflowEvent {
            +int id
            +int instance_id
            +string node_key
            +string type
            +array payload
            +BelongsTo instance
        }

        class WorkflowTemplate {
            +int id
            +string name
            +string description
            +string category
            +array definition
            +bool is_active
            +int usage_count
            +BelongsTo tenant
            +BelongsTo createdBy
        }

        class Node {
            +int id
            +string type
            +string label
            +NodeCategory category
            +string description
            +string color
            +string icon
            +bool is_active
            +HasMany configFields
        }

        class NodeConfigField {
            +int id
            +int node_id
            +string key
            +string label
            +NodeConfigFieldType type
            +string placeholder
            +array options
            +bool is_required
            +mixed default_value
            +int sort_order
            +BelongsTo node
        }

        class WorkflowDynamicFlow {
            +int id
            +int instance_id
            +int execution_id
            +string node_key
            +DynamicFlowStatus status
            +array definition
            +BelongsTo instance
            +BelongsTo execution
            +BelongsTo createdBy
            +BelongsTo childInstance
        }
    }

    %% ============================================================
    %% ENUMS
    %% ============================================================
    namespace Enums {
        class WorkflowStatus {
            <<enum>>
            Active
            Disabled
            Deleted
        }

        class WorkflowInstanceStatus {
            <<enum>>
            Pending
            Running
            Waiting
            Paused
            Completed
            Failed
            Cancelled
            +isTerminal() bool
        }

        class NodeCategory {
            <<enum>>
            Trigger
            Logic
            Ai
            Flows
            Action
        }

        class NodeExecutionStatus {
            <<enum>>
            Pending
            Running
            Succeeded
            Failed
            Waiting
            Skipped
            Consumed
            +isLive() bool
            +isTerminal() bool
        }

        class TriggerType {
            <<enum>>
            Manual
            Form
            Webhook
            SubWorkflow
        }

        class EdgeBranchType {
            <<enum>>
            Default
            Conditional
            Parallel
        }

        class WaitType {
            <<enum>>
            TaskSla
            MergeTimeout
            SubWorkflow
            DynamicFlowDesign
        }

        class ResultKind {
            <<enum>>
            Proceed
            Branch
            Wait
            Fail
            Terminate
            Noop
        }

        class VerificationMode {
            <<enum>>
            Full
            Segment
        }

        class DynamicFlowStatus {
            <<enum>>
            AwaitingDesign
            Executing
            Completed
            Cancelled
        }

        class NodeConfigFieldType {
            <<enum>>
            TEXT
            TEXTAREA
            SELECT
            TOGGLE
            NUMBER
            TAGS
            JSON
            EMAIL
            BRANCHES
            READONLY
        }
    }

    %% ============================================================
    %% SERVICES
    %% ============================================================
    namespace Services {
        class WorkflowManagementService {
            -WorkflowDefinitionValidator validator
            -WorkflowDispatcher dispatcher
            +listVisibleWorkflows(User, array) Collection
            +validateDefinition(array, Workflow, User) array
            +createWorkflow(User, array) Workflow
            +generateAiProposal(User, array) array
            +updateDraft(User, Workflow, array) array
            +publish(User, Workflow, array) WorkflowVersion
            +updateStatus(User, Workflow, string) Workflow
            +softDelete(User, Workflow) Workflow
            +purge(User, Workflow) void
            +triggerWebhook(User, Workflow, array) WorkflowInstance
            +triggerManual(User, Workflow, array) WorkflowInstance
            +triggerForm(User, Workflow, array) WorkflowInstance
            +serializeWorkflow(Workflow, User, bool) array
        }

        class WorkflowDefinitionValidator {
            -WorkflowVerificationService verifier
            +validate(array, Workflow, User, VerificationMode) array
        }

        class NodeDefinitionService {
            +listActiveNodes() Collection
        }

        class PublicFormService {
            -WorkflowDispatcher dispatcher
            +findPublicForm(string) Workflow
            +formSchema(Workflow) array
            +validateSubmission(Workflow, array) array
            +submit(Workflow, array) WorkflowInstance
        }
    }

    %% ============================================================
    %% VERIFICATION SUBSYSTEM
    %% ============================================================
    namespace Verification {
        class WorkflowVerificationService {
            -WorkflowDefinitionNormalizer normalizer
            -array rules
            -array nodeTypeRules
            +verify(array, Workflow, User, VerificationMode) WorkflowVerificationResult
            +rules() array
        }

        class VerificationRule {
            <<interface>>
            +verify(array, WorkflowDefinitionGraph, WorkflowVerificationResult, Workflow, User, VerificationMode) void
        }

        class SyntaxVerificationRule {
            +verify() void
        }

        class GraphControlFlowVerificationRule {
            +verify() void
        }

        class StructuredControlFlowVerificationRule {
            -ControlFlowReducer reducer
            +verify() void
        }

        class ExpressionVerificationRule {
            -ExpressionLanguageValidator validator
            +verify() void
        }

        class ContextualVerificationRule {
            +verify() void
        }

        class NodeTypeVerificationRule {
            -array nodeTypeRules
            +register(NodeTypeRule) void
            +verify() void
        }

        class DataFlowVerificationRule {
            -DataFlowAnalyzer analyzer
            +verify() void
        }

        class FormTriggerVerificationRule {
            +verify() void
        }

        class NodeTypeRule {
            <<interface>>
            +nodeType() string
            +verify(array, int, WorkflowDefinitionGraph, WorkflowVerificationResult, Workflow) void
        }

        class IfNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class ForkNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class SwitchNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class MergeNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class TaskNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class SendEmailNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class AiGeneratorNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class TerminationNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class SubWorkflowNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class DynamicEntryNodeTypeRule {
            +nodeType() string
            +verify() void
        }

        class VariableAvailability {
            <<trait>>
            +validateVariableNamespace() bool
            +validateContextVariableExists() void
            +warnIfParallelPaths() void
            +collectAvailableContextKeys() array
            +validateTemplateVariables() void
            +hasParallelPaths() bool
        }

        class WorkflowDefinitionNormalizer {
            +normalize(array) array
        }

        class WorkflowDefinitionGraph {
            #array nodesById
            #array outgoing
            #array incoming
            +definition() array
            +trigger() array
            +nodes() array
            +edges() array
            +hasNode(string) bool
            +node(string) array
            +outgoing(string) array
            +incoming(string) array
            +entryNodeIds() list
            +terminalNodeIds() list
            +reachableFrom(string) list
            +topologicalOrder() list
            +invalidCycleEdges() array
        }

        class ExpressionLanguageValidator {
            #array tokens
            #int position
            #array variables
            +validateBoolean(string) ExpressionValidationResult
        }

        class ExpressionValidationResult {
            +bool valid
            +bool boolean
            +array variables
            +string message
        }

        class WorkflowVerificationResult {
            -array issues
            +add(WorkflowViolation) void
            +addError() void
            +addWarning() void
            +issues() array
            +isPublishable() bool
            +toArray() array
        }

        class WorkflowViolation {
            +string severity
            +string code
            +string message
            +string path
            +string nodeId
            +string edgeId
            +error() WorkflowViolation$
            +warning() WorkflowViolation$
            +toArray() array
        }

        class ControlFlowReducer {
            -WorkflowDefinitionGraph graph
            +reduce() ControlFlowReductionResult
        }

        class ControlFlowReductionResult {
            +array matched
            +array mismatches
            +array unmatchedSplits
            +array unmatchedMerges
        }

        class DataFlowAnalyzer {
            -WorkflowDefinitionGraph graph
            +analyze() DataFlowResult
        }

        class DataFlowResult {
            +array guaranteed
            +array possible
            +array conflicts
            +array producers
            +guaranteedAt(string) list
        }
    }

    %% ============================================================
    %% EXECUTION ENGINE
    %% ============================================================
    namespace Execution {
        class WorkflowDispatcher {
            -ExecutionPlanCompiler compiler
            -NodeExecutorRegistry registry
            -InstanceAdmissionService admission
            +dispatch(Workflow, TriggerType, array, string, int, int, array) WorkflowInstance
        }

        class WorkflowRuntime {
            -ExecutionPlanCompiler compiler
            -NodeExecutorRegistry registry
            -ExpressionEvaluator evaluator
            -TemplateInterpolator interpolator
            -FailureClassifier classifier
            -RetryPolicy retryPolicy
            +advance(int) void
            +cancel(WorkflowInstance) void
            +retryFromNode(WorkflowInstance, string) void
        }

        class NodeExecutor {
            <<interface>>
            +type() string
            +category() NodeCategory
            +execute(NodeExecutionContext) NodeExecutionResult
        }

        class NodeExecutorRegistry {
            #array executors
            +register(NodeExecutor) self
            +registerAs(string, NodeExecutor) self
            +has(string) bool
            +for(string) NodeExecutor
            +registeredTypes() list
        }

        class NodeExecutionContext {
            -WorkflowInstance instance
            -WorkflowNodeExecution execution
            -ExecutionPlan plan
            -ExpressionEvaluator evaluator
            -TemplateInterpolator interpolator
            +instance() WorkflowInstance
            +execution() WorkflowNodeExecution
            +plan() ExecutionPlan
            +config() array
            +evaluate(string) mixed
            +evaluateBoolean(string) bool
            +render(string) string
            +setContextValue(string, mixed) void
            +mergeContext(array) void
        }

        class NodeExecutionResult {
            <<final>>
            +ResultKind kind
            +array edges
            +array output
            +Throwable error
            +string errorMessage
            +bool retryable
            +DateTimeInterface waitUntil
            +WaitType waitType
            +string reason
            +proceed(array, array)$ NodeExecutionResult
            +branch(PlanEdge, array)$ NodeExecutionResult
            +wait(DateTimeInterface, WaitType, string)$ NodeExecutionResult
            +fail(Throwable|string, bool)$ NodeExecutionResult
            +terminate(array)$ NodeExecutionResult
            +noop()$ NodeExecutionResult
        }

        class ExecutionPlan {
            #WorkflowDefinitionGraph graph
            #array nodesByKey
            #array outgoing
            #array incoming
            +triggerNodeKey() string
            +entryNodeKeys() array
            +nodeKeys() array
            +hasNode(string) bool
            +config(string) array
            +outgoing(string) list
            +isTerminal(string) bool
            +forkBranches(string) list
            +isMergeNode(string) bool
            +joinFor(string) JoinSpec
        }

        class PlanEdge {
            <<final>>
            +string id
            +string source
            +string target
            +string branchType
            +string conditionExpression
            +bool isDefaultBranch
            +string joinNodeKey
            +int sortOrder
            +fromNormalized(array)$ PlanEdge
            +isConditional() bool
            +isParallel() bool
        }

        class JoinSpec {
            <<final>>
            +string mode
            +int expectedCount
            +int timeoutSeconds
            +isParallel() bool
            +MODE_PARALLEL$
            +MODE_CONDITIONAL$
        }

        class FailureClassifier {
            +isRetryable(Throwable) bool
        }

        class RetryPolicy {
            +shouldRetry(int) bool
            +maxAttempts() int
            +delaySeconds(int) int
        }
    }

    %% ============================================================
    %% CONTROLLERS
    %% ============================================================
    namespace Controllers {
        class WorkflowController {
            -WorkflowManagementService service
            +index(Request) JsonResponse
            +templates() JsonResponse
            +store(StoreWorkflowRequest) JsonResponse
            +proposal(AiProposalRequest) JsonResponse
            +validateDefinition(ValidateWorkflowDefinitionRequest) JsonResponse
            +show(Workflow) JsonResponse
            +updateDraft(UpdateDraftRequest, Workflow) JsonResponse
            +publish(PublishWorkflowRequest, Workflow) JsonResponse
            +versions(Workflow) JsonResponse
            +updateStatus(UpdateWorkflowStatusRequest, Workflow) JsonResponse
            +destroy(Workflow) JsonResponse
            +purge(Workflow) JsonResponse
            +triggerWebhook(Request, Workflow) JsonResponse
        }

        class WorkflowInstanceController {
            -WorkflowRuntime runtime
            -WorkflowManagementService service
            +index(ListWorkflowInstancesRequest, Workflow) JsonResponse
            +show(WorkflowInstance) JsonResponse
            +failures(WorkflowInstance) JsonResponse
            +cancel(WorkflowInstance) JsonResponse
            +retryFromNode(Request, WorkflowInstance) JsonResponse
        }

        class WorkflowTaskController {
            +index(ListTasksRequest) ResourceCollection
            +summary() JsonResponse
            +show(WorkflowTask) Resource
            +saveDraft(SaveTaskDraftRequest, WorkflowTask) JsonResponse
            +submit(SubmitTaskRequest, WorkflowTask) JsonResponse
        }

        class NodeController {
            -NodeDefinitionService service
            +index() JsonResponse
        }

        class WorkflowTriggerController {
            -WorkflowManagementService service
            +manual(Request, Workflow) JsonResponse
            +form(Request, Workflow) JsonResponse
        }

        class DynamicFlowController {
            +show(Request, WorkflowInstance) JsonResponse
            +storeDefinition(Request, WorkflowInstance) JsonResponse
        }

        class PublicFormController {
            -PublicFormService service
            +show(string) JsonResponse
            +submit(Request, string) JsonResponse
        }
    }

    %% ============================================================
    %% EVENTS
    %% ============================================================
    namespace Events {
        class InstanceStarted {
            +WorkflowInstance instance
        }
        class InstanceCompleted {
            +WorkflowInstance instance
        }
        class InstanceFailed {
            +WorkflowInstance instance
        }
        class InstanceCancelled {
            +WorkflowInstance instance
        }
        class InstancePaused {
            +WorkflowInstance instance
            +string reason
        }
        class NodeStarted {
            +WorkflowInstance instance
            +WorkflowNodeExecution execution
        }
        class NodeCompleted {
            +WorkflowInstance instance
            +WorkflowNodeExecution execution
        }
        class NodeFailed {
            +WorkflowInstance instance
            +WorkflowNodeExecution execution
            +bool willRetry
        }
        class NodeWaiting {
            +WorkflowInstance instance
            +WorkflowNodeExecution execution
        }
        class NodeRetrying {
            +WorkflowInstance instance
            +WorkflowNodeExecution failedExecution
            +WorkflowNodeExecution retryExecution
        }
    }

    %% ============================================================
    %% JOBS & NOTIFICATIONS
    %% ============================================================
    namespace Infrastructure {
        class ExecuteNodeJob {
            <<ShouldQueue>>
            +int executionId
            +string nodeCategory
            +handle(WorkflowRuntime) void
        }

        class DynamicFlowDesignRequested {
            <<Notification>>
            +WorkflowInstance instance
            +WorkflowDynamicFlow dynamicFlow
        }
    }

    %% ============================================================
    %% RELATIONSHIPS — Models
    %% ============================================================
    Workflow "1" --> "0..1" WorkflowVersion : currentVersion
    Workflow "1" --> "*" WorkflowVersion : versions
    Workflow "1" --> "*" WorkflowInstance : instances
    Workflow "1" --> "*" WorkflowNode : nodes
    Workflow "1" --> "*" WorkflowEdge : edges
    WorkflowTemplate "1" --> "*" Workflow : templates
    WorkflowVersion "1" --> "*" WorkflowNode : nodes
    WorkflowVersion "1" --> "*" WorkflowEdge : edges

    WorkflowInstance "1" --> "0..1" WorkflowVersion : workflowVersion
    WorkflowInstance "1" --> "*" WorkflowNodeExecution : nodeExecutions
    WorkflowInstance "1" --> "*" WorkflowTask : tasks
    WorkflowInstance "1" --> "*" WorkflowEvent : events
    WorkflowInstance "1" --> "0..1" WorkflowInstance : parent
    WorkflowInstance "1" --> "0..1" WorkflowDynamicFlow : dynamicFlow

    WorkflowNode "1" --> "*" WorkflowEdge : outgoingEdges
    WorkflowNode "1" --> "*" WorkflowEdge : incomingEdges
    WorkflowNode "*" --> "1" Node : nodeType

    Node "1" --> "*" NodeConfigField : configFields

    WorkflowNodeExecution "1" --> "0..1" WorkflowNodeExecution : parent
    WorkflowNodeExecution "1" --> "0..1" WorkflowTask : task

    WorkflowTask "*" --> "1" WorkflowNodeExecution : execution

    WorkflowDynamicFlow "1" --> "0..1" WorkflowNodeExecution : execution

    %% ============================================================
    %% RELATIONSHIPS — Verification Pipeline
    %% ============================================================
    WorkflowVerificationService *-- "1" WorkflowDefinitionNormalizer
    WorkflowVerificationService *-- "1" NodeTypeVerificationRule
    WorkflowVerificationService --> "1" WorkflowVerificationResult : produces

    VerificationRule <|.. SyntaxVerificationRule
    VerificationRule <|.. GraphControlFlowVerificationRule
    VerificationRule <|.. StructuredControlFlowVerificationRule
    VerificationRule <|.. ExpressionVerificationRule
    VerificationRule <|.. ContextualVerificationRule
    VerificationRule <|.. NodeTypeVerificationRule
    VerificationRule <|.. DataFlowVerificationRule
    VerificationRule <|.. FormTriggerVerificationRule

    NodeTypeVerificationRule o-- "*" NodeTypeRule : dispatches to

    NodeTypeRule <|.. IfNodeTypeRule
    NodeTypeRule <|.. ForkNodeTypeRule
    NodeTypeRule <|.. SwitchNodeTypeRule
    NodeTypeRule <|.. MergeNodeTypeRule
    NodeTypeRule <|.. TaskNodeTypeRule
    NodeTypeRule <|.. SendEmailNodeTypeRule
    NodeTypeRule <|.. AiGeneratorNodeTypeRule
    NodeTypeRule <|.. TerminationNodeTypeRule
    NodeTypeRule <|.. SubWorkflowNodeTypeRule
    NodeTypeRule <|.. DynamicEntryNodeTypeRule

    IfNodeTypeRule ..|> VariableAvailability
    SwitchNodeTypeRule ..|> VariableAvailability
    SendEmailNodeTypeRule ..|> VariableAvailability
    AiGeneratorNodeTypeRule ..|> VariableAvailability
    SubWorkflowNodeTypeRule ..|> VariableAvailability

    StructuredControlFlowVerificationRule --> ControlFlowReducer
    ControlFlowReducer --> ControlFlowReductionResult
    DataFlowVerificationRule --> DataFlowAnalyzer
    DataFlowAnalyzer --> DataFlowResult
    ExpressionVerificationRule --> ExpressionLanguageValidator
    ExpressionLanguageValidator --> ExpressionValidationResult

    WorkflowVerificationResult *-- "*" WorkflowViolation

    %% ============================================================
    %% RELATIONSHIPS — Execution Engine
    %% ============================================================
    WorkflowManagementService --> WorkflowDefinitionValidator : validates via
    WorkflowManagementService --> WorkflowDispatcher : triggers via
    WorkflowDefinitionValidator --> WorkflowVerificationService : delegates to

    WorkflowDispatcher --> ExecutionPlanCompiler
    WorkflowDispatcher --> NodeExecutorRegistry
    WorkflowDispatcher --> WorkflowInstance : creates

    WorkflowRuntime --> ExecutionPlanCompiler
    WorkflowRuntime --> NodeExecutorRegistry
    WorkflowRuntime --> ExpressionEvaluator
    WorkflowRuntime --> TemplateInterpolator
    WorkflowRuntime --> FailureClassifier
    WorkflowRuntime --> RetryPolicy

    NodeExecutorRegistry o-- "*" NodeExecutor : holds

    NodeExecutionContext --> WorkflowInstance
    NodeExecutionContext --> WorkflowNodeExecution
    NodeExecutionContext --> ExecutionPlan

    ExecutionPlan --> ExecutionPlanCompiler
    ExecutionPlan --> PlanEdge
    ExecutionPlan --> JoinSpec

    %% ============================================================
    %% RELATIONSHIPS — Events → Instance
    %% ============================================================
    InstanceStarted --> WorkflowInstance
    InstanceCompleted --> WorkflowInstance
    InstanceFailed --> WorkflowInstance
    InstanceCancelled --> WorkflowInstance
    InstancePaused --> WorkflowInstance
    NodeStarted --> WorkflowInstance
    NodeStarted --> WorkflowNodeExecution
    NodeCompleted --> WorkflowInstance
    NodeCompleted --> WorkflowNodeExecution
    NodeFailed --> WorkflowInstance
    NodeFailed --> WorkflowNodeExecution
    NodeWaiting --> WorkflowInstance
    NodeWaiting --> WorkflowNodeExecution
    NodeRetrying --> WorkflowNodeExecution

    %% ============================================================
    %% RELATIONSHIPS — Controllers → Services
    %% ============================================================
    WorkflowController --> WorkflowManagementService
    WorkflowInstanceController --> WorkflowRuntime
    WorkflowInstanceController --> WorkflowManagementService
    NodeController --> NodeDefinitionService
    WorkflowTriggerController --> WorkflowManagementService
    DynamicFlowController --> WorkflowDefinitionValidator
    DynamicFlowController --> WorkflowDispatcher
    PublicFormController --> PublicFormService
    PublicFormService --> WorkflowDispatcher

    %% ============================================================
    %% RELATIONSHIPS — Jobs
    %% ============================================================
    ExecuteNodeJob --> WorkflowRuntime : invokes
    DynamicFlowDesignRequested --> WorkflowInstance
    DynamicFlowDesignRequested --> WorkflowDynamicFlow
```
