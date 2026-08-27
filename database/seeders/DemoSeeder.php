<?php

namespace Database\Seeders;

use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\Demo\DemoDocumentLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Customers\Enums\CustomerLinkingField;
use Modules\Customers\Models\CustomerSettings;
use Modules\Integrations\Database\Seeders\IntegrationsDatabaseSeeder;
use Modules\Integrations\Models\IntegrationConnection;
use Modules\KnowledgeBase\Database\Seeders\DocumentTypeSeeder;
use Modules\KnowledgeBase\Models\Document;
use Modules\KnowledgeBase\Models\DocumentType;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Database\Seeders\NodeDefinitionSeeder;
use Modules\Workflows\Enums\VerificationMode;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowTemplate;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Verification\WorkflowVerificationService;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Seeds the "Company" demo tenant: two cross-functional departments (Sales + IT), their people,
 * the integrations/knowledge-base rows the nodes depend on, and three published workflow artifacts.
 *
 * The definitions are kept deliberately small — a demo canvas has to be readable from across a room.
 * Between them they exercise every seeded node type except `parse-json`, which is left out on purpose:
 * no node here emits a JSON string worth parsing, and adding one only to have something to parse would
 * be padding. Node types repeat only where the engine forces it (see the merge note below).
 *
 *   1. Child workflow  — "Company — Standard Estimation Pack v1" (IT team, manual-trigger).
 *      Published first, because the parent's `sub-workflow` node needs a real, published workflowId.
 *   2. Parent workflow — "Company — Pre-Sales: RFP to Proposal" (Sales team, form-trigger).
 *   3. Segment template — "Company — On-Prem Expert Review" (dynamic-entry), the runtime sub-flow a
 *      manager composes inside the parent's `dynamic-flow` node, stored as a reusable template.
 *
 * Every definition is run through WorkflowVerificationService before being persisted, and the seeder
 * aborts loudly if any of them would fail to publish, so this data can never drift into an invalid state.
 *
 * Structural constraints this file is built around (see Modules/Workflows/docs/README_VERIFICATION.md):
 *  - StructuredControlFlowVerificationRule reduces the graph to a fixpoint, so every split must be a
 *    strictly nested SESE block: an if/switch converges on ONE `conditional` merge, an and-node on ONE
 *    `parallel` merge, and a merge's only predecessors are that split's own branches. That is why every
 *    branch rejoins instead of terminating on its own, and there is a single terminal node per workflow.
 *  - A switch needs exactly `count(options) + 1` outgoing edges (the extra one is the default lane).
 *  - Template variables are a SINGLE identifier: `{{context.foo}}` is valid, `{{context.foo.bar}}` is not
 *    (VariableAvailability::validateTemplateVariables). Dotted paths are only legal inside if/switch
 *    expressions and the `variable` field, which are parsed rather than interpolated.
 *  - DataFlowVerificationRule only lets a node read a produced variable if it is guaranteed on every
 *    incoming path. That is why all three switch lanes converge on the same `estimationSummary` variable
 *    before the proposal generator reads it.
 *  - Every split costs a merge, so the parent's three merges are the floor for its switch + fork + if,
 *    not duplication that could be tidied away. An if/switch branch may be empty and run straight into
 *    its merge, which is how the rejected approval path avoids needing a node of its own.
 *  - `knowledgeBaseDocuments` must be numeric Document ids belonging to the tenant, not titles.
 *  - task-node `assignTo` must be an active user who is an active member of the workflow's OWN team.
 *
 * Known platform gaps this data deliberately works around (both are pre-existing, not seeder bugs):
 *  - HubSpotCreateContactExecutor/HubSpotCreateDealExecutor return their new ids as node *result output*
 *    (`hubspot_contact_id`), which the engine stores at `context.<nodeKey>`, not as a flat
 *    `context.hubspotContactId`. Combined with the single-identifier template rule, the contact id
 *    cannot currently be piped into the deal node. The reference is kept because it renders to an empty
 *    string and HubSpotCreateDealExecutor skips a non-numeric contactId, so the deal is still created.
 *  - TaskNodeExecutor merges each submitted task response into the single `context.task_response` key, so
 *    on the parallel review branch only the last branch to finish is readable by name downstream.
 */
class DemoSeeder extends Seeder
{
    /**
     * The tenant this seeder owns, end to end. Everything the reset deletes is scoped to the tenant
     * carrying this exact business name, so no other tenant's data is ever in range.
     */
    private const TENANT_NAME = 'Company';

    private const PASSWORD = 'P@ssword123';

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, int> */
    private array $documents = [];

    private Tenant $tenant;

    private Team $salesTeam;

    private Team $itTeam;

    public function run(): void
    {
        // The demo data leans on the node catalog, the integration providers and the KB document types.
        $this->call([
            NodeDefinitionSeeder::class,
            IntegrationsDatabaseSeeder::class,
            DocumentTypeSeeder::class,
        ]);

        $this->resetTenant();

        $this->seedTenant();
        $this->seedPeopleAndTeams();
        $this->seedIntegrations();
        $this->seedKnowledgeBase();

        $child = $this->seedChildWorkflow();
        $this->seedParentWorkflow((int) $child->id);
        $this->seedExpertReviewTemplate();

        $this->command?->info(sprintf(
            'Demo tenant "%s" seeded (id %d). Sign in with any @company.example address / %s',
            self::TENANT_NAME,
            $this->tenant->id,
            self::PASSWORD,
        ));
    }

    /**
     * Wipes every trace of the demo tenant so `run()` always builds on bare ground.
     *
     * Scoped strictly to the tenant whose `business_name` is self::TENANT_NAME — no other tenant, and
     * none of the shared catalog rows (node definitions, integration providers, document types, global
     * workflow templates), is ever in range.
     *
     * The order below is not arbitrary and is not safe to shuffle. Most tenant-scoped tables cascade
     * from `tenants`, but five foreign keys are RESTRICT and would abort the delete if their dependents
     * were still present:
     *
     *   workflows.team_id            -> teams          RESTRICT
     *   workflows.created_by_id      -> users          RESTRICT
     *   workflow_versions.published_by_id -> users     RESTRICT
     *   workflow_instances.workflow_version_id -> workflow_versions RESTRICT
     *   teams.manager_id             -> users          RESTRICT
     *
     * So: runtime rows before design-time rows, workflows before teams, and everything before users.
     * Deleting explicitly (rather than leaning on `$tenant->delete()` and letting the database unwind
     * the cascade) also means this behaves identically on PostgreSQL and on SQLite, where foreign key
     * enforcement is not guaranteed to be on.
     */
    private function resetTenant(): void
    {
        $tenants = Tenant::query()->where('business_name', self::TENANT_NAME)->get();

        if ($tenants->isEmpty()) {
            return;
        }

        foreach ($tenants as $tenant) {
            $this->purgeTenant($tenant);
        }
    }

    private function purgeTenant(Tenant $tenant): void
    {
        $tenantId = (int) $tenant->id;

        // Stored files first: once the document rows are gone there is no record of which paths were ours.
        $disk = Storage::disk(config('filesystems.default'));

        foreach (Document::query()->where('tenant_id', $tenantId)->pluck('file_path') as $path) {
            $disk->delete((string) $path);
        }

        $disk->deleteDirectory("documents/{$tenantId}");

        // Captured before anything is deleted — these drive the tables that have no tenant_id of their own.
        $userIds = DB::table('users')->where('tenant_id', $tenantId)->pluck('id');
        $workflowIds = DB::table('workflows')->where('tenant_id', $tenantId)->pluck('id');
        $instanceIds = DB::table('workflow_instances')->where('tenant_id', $tenantId)->pluck('id');
        $documentIds = DB::table('documents')->where('tenant_id', $tenantId)->pluck('id');
        $userMorph = (new User)->getMorphClass();

        DB::transaction(function () use ($tenantId, $userIds, $workflowIds, $instanceIds, $documentIds, $userMorph): void {
            // 1. Execution runtime — deepest dependents first.
            DB::table('workflow_events')->where('tenant_id', $tenantId)->delete();
            DB::table('workflow_instance_comments')->whereIn('workflow_instance_id', $instanceIds)->delete();
            DB::table('workflow_instance_attachments')->whereIn('workflow_instance_id', $instanceIds)->delete();
            DB::table('workflow_tasks')->where('tenant_id', $tenantId)->delete();
            DB::table('workflow_dynamic_flows')->where('tenant_id', $tenantId)->delete();
            DB::table('workflow_node_executions')->where('tenant_id', $tenantId)->delete();
            DB::table('workflow_instances')->where('tenant_id', $tenantId)->delete();

            // 2. Design-time graph. `workflows.current_version_id` points back at a version, so break
            //    that link before removing the versions it references.
            DB::table('workflow_edges')->whereIn('workflow_id', $workflowIds)->delete();
            DB::table('workflow_nodes')->whereIn('workflow_id', $workflowIds)->delete();
            DB::table('workflows')->where('tenant_id', $tenantId)->update(['current_version_id' => null]);
            DB::table('workflow_versions')->where('tenant_id', $tenantId)->delete();
            DB::table('workflows')->where('tenant_id', $tenantId)->delete();
            // Tenant-owned templates only; global templates have a null tenant_id and are left alone.
            DB::table('workflow_templates')->where('tenant_id', $tenantId)->delete();

            // 3. Knowledge base.
            DB::table('document_tag')->whereIn('document_id', $documentIds)->delete();
            DB::table('documents')->where('tenant_id', $tenantId)->delete();
            DB::table('tags')->where('tenant_id', $tenantId)->delete();

            // 4. Customers.
            DB::table('customers')->where('tenant_id', $tenantId)->delete();
            DB::table('customer_fields')->where('tenant_id', $tenantId)->delete();
            DB::table('customer_settings')->where('tenant_id', $tenantId)->delete();

            // 5. Integrations (the connection rows; the shared providers stay).
            DB::table('integration_connections')->where('tenant_id', $tenantId)->delete();

            // 6. Org chart. Teams must go after workflows (RESTRICT) and before users (RESTRICT).
            DB::table('team_memberships')->where('tenant_id', $tenantId)->delete();
            DB::table('teams')->where('tenant_id', $tenantId)->delete();
            DB::table('audit_trails')->where('tenant_id', $tenantId)->delete();
            DB::table('platform_announcements')->where('target_tenant_id', $tenantId)->delete();

            // 7. Rows hanging off the users with no foreign key to cascade them.
            DB::table('notifications')
                ->where('notifiable_type', $userMorph)
                ->whereIn('notifiable_id', $userIds)
                ->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $userMorph)
                ->whereIn('tokenable_id', $userIds)
                ->delete();
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            DB::table('device_tokens')->whereIn('user_id', $userIds)->delete();

            DB::table('users')->where('tenant_id', $tenantId)->delete();
            DB::table('tenants')->where('id', $tenantId)->delete();
        });

        $this->command?->warn(sprintf(
            'Deleted existing demo tenant "%s" (id %d): %d user(s), %d workflow(s), %d instance(s), %d document(s).',
            self::TENANT_NAME,
            $tenantId,
            $userIds->count(),
            $workflowIds->count(),
            $instanceIds->count(),
            $documentIds->count(),
        ));
    }

    private function seedTenant(): void
    {
        $this->tenant = Tenant::query()->updateOrCreate(
            ['business_name' => self::TENANT_NAME],
            [
                'business_type' => BusinessType::SoftwareHouse,
                'is_active' => true,
                'maintenance_mode' => false,
            ]
        );

        // The parent workflow's form trigger links submissions to a Customer by email.
        CustomerSettings::query()->updateOrCreate(
            ['tenant_id' => (int) $this->tenant->id],
            ['linking_field' => CustomerLinkingField::Email],
        );
    }

    private function seedPeopleAndTeams(): void
    {
        $people = [
            // key            first      last        position                       role
            'owner' => ['Dana', 'Sabbagh', 'Managing Director', Role::BusinessOwner],
            'salesManager' => ['Nadia', 'Haddad', 'Pre-Sales Lead', Role::Manager],
            'accountExec' => ['Omar', 'Khalil', 'Account Executive', Role::Employee],
            'pricingAnalyst' => ['Lina', 'Barakat', 'Commercial Analyst', Role::Employee],
            'architect' => ['Sara', 'Mansour', 'Solution Architect', Role::Employee],
            'itManager' => ['Yusuf', 'Rahman', 'Head of Engineering', Role::Manager],
            'cloudEngineer' => ['Karim', 'Aziz', 'Cloud Engineer', Role::Employee],
            'deliveryLead' => ['Rami', 'Nasser', 'Delivery Lead', Role::Employee],
        ];

        foreach ($people as $key => [$first, $last, $position, $role]) {
            $this->users[$key] = User::query()->updateOrCreate(
                ['email' => strtolower($first).'.'.strtolower($last).'@company.example'],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'name' => "{$first} {$last}",
                    'position' => $position,
                    'password' => Hash::make(self::PASSWORD),
                    'role' => $role,
                    'is_active' => true,
                    'tenant_id' => (int) $this->tenant->id,
                ]
            );
        }

        $this->salesTeam = Team::query()->updateOrCreate(
            ['tenant_id' => (int) $this->tenant->id, 'name' => 'Sales Department'],
            [
                'description' => 'Pre-sales, solution design and commercial ownership of inbound RFPs.',
                'manager_id' => (int) $this->users['salesManager']->id,
            ]
        );

        $this->itTeam = Team::query()->updateOrCreate(
            ['tenant_id' => (int) $this->tenant->id, 'name' => 'IT Department'],
            [
                'description' => 'Engineering, architecture and infrastructure delivery.',
                'manager_id' => (int) $this->users['itManager']->id,
            ]
        );

        // `team_memberships` is unique on (tenant_id, user_id): a user belongs to exactly ONE team.
        // Combined with ContextualVerificationRule — which requires every task assignee to be an active
        // member of the workflow's own team — that means a single workflow can only assign work inside
        // one department. Cross-department work therefore happens through the hops that cross a team
        // boundary by design: the `sub-workflow` node calling the IT-owned estimation pack, the
        // `clickup-create-task` node landing in the delivery list, and the `dynamic-flow` node, whose
        // runtime segment is where a manager pulls in whichever specialists the deal actually needs.
        // The solution architect sits inside the pre-sales pod, which is why she is on the Sales team.
        foreach (['salesManager', 'accountExec', 'pricingAnalyst', 'architect'] as $key) {
            $this->joinTeam($this->salesTeam, $this->users[$key]);
        }

        foreach (['itManager', 'cloudEngineer', 'deliveryLead'] as $key) {
            $this->joinTeam($this->itTeam, $this->users[$key]);
        }
    }

    private function joinTeam(Team $team, User $user): void
    {
        TeamMembership::query()->updateOrCreate(
            // Unique on (tenant_id, user_id) — key on that so re-seeding moves a member rather than failing.
            [
                'tenant_id' => (int) $this->tenant->id,
                'user_id' => (int) $user->id,
            ],
            ['team_id' => (int) $team->id, 'status' => 'active'],
        );
    }

    /**
     * The hubspot-* and clickup-* node type rules refuse to publish unless the tenant has a connection
     * row for that provider, so the demo tenant gets placeholder connections. The tokens are obviously
     * fake — swap them for real ones before running the integration nodes for real.
     */
    private function seedIntegrations(): void
    {
        $connections = [
            'hubspot' => [
                'auth_config' => ['access_token' => 'demo-hubspot-token', 'refresh_token' => 'demo-hubspot-refresh'],
                'config' => ['portal_id' => '24601000'],
            ],
            'clickup' => [
                'auth_config' => ['access_token' => 'demo-clickup-token'],
                'config' => ['teams' => [['id' => '9012345678', 'name' => 'Company']]],
            ],
            'google' => [
                'auth_config' => ['access_token' => 'demo-google-token', 'refresh_token' => 'demo-google-refresh'],
                'config' => ['email' => 'presales@company.example'],
            ],
        ];

        foreach ($connections as $provider => $payload) {
            IntegrationConnection::query()->updateOrCreate(
                ['integration_provider_id' => $provider, 'tenant_id' => (int) $this->tenant->id],
                $payload,
            );
        }
    }

    /**
     * The AI nodes ground their prompts in knowledge-base documents. ContextualVerificationRule checks
     * that every id in `knowledgeBaseDocuments` is a real Document for this tenant, so they are seeded
     * first and referenced by id.
     *
     * Each one is a real, rendered PDF written to the same place and in the same shape a genuine upload
     * would land — `documents/{tenantId}/{uuid}.pdf` on `config('filesystems.default')`, matching
     * DocumentService::storeFile() — so the download endpoint, the RAG indexer and the frontend viewer
     * all treat them exactly like user-uploaded files. The content lives in DemoDocumentLibrary and is
     * written to agree with the prompts that cite it.
     *
     * The UUID is derived from the document key rather than random, so re-running the seeder overwrites
     * the same six files instead of littering the disk with orphans.
     */
    private function seedKnowledgeBase(): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $tenantId = (int) $this->tenant->id;

        foreach (DemoDocumentLibrary::documents() as $key => $meta) {
            $typeId = (int) DocumentType::query()->firstOrCreate(['name' => $meta['type']])->id;

            $path = sprintf(
                'documents/%d/%s.pdf',
                $tenantId,
                Uuid::uuid5(Uuid::NAMESPACE_URL, "company-demo/{$tenantId}/{$key}")->toString(),
            );

            $disk->put($path, Pdf::loadHTML(DemoDocumentLibrary::render($key))
                ->setPaper('a4')
                // Set per render rather than in config/dompdf.php: subsetting takes these from ~900 KB
                // (whole DejaVu families embedded) to ~40 KB each, without changing how the rest of the
                // app generates PDFs.
                ->setOption('enable_font_subsetting', true)
                ->output());

            $document = Document::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'title' => $meta['title']],
                [
                    'document_type_id' => $typeId,
                    'file_path' => $path,
                    'is_active' => true,
                    // Nothing has been sent to the RAG service for these, so don't claim otherwise.
                    'index_status' => 'pending',
                ]
            );

            $this->documents[$key] = (int) $document->id;
        }

        $this->command?->info(sprintf(
            'Rendered %d knowledge-base PDFs to %s/documents/%d/',
            count(DemoDocumentLibrary::documents()),
            rtrim((string) config('filesystems.disks.'.config('filesystems.default').'.root'), '/'),
            $tenantId,
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Child workflow — "Company — Standard Estimation Pack v1" (IT Department)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * A small, genuinely reusable workflow so the parent's `sub-workflow` node has something real to
     * call. It uses a manual-trigger with declared `variables` on purpose: the frontend's
     * SubWorkflowEditor reads its input-mapping pills from exactly that field.
     *
     * Note that a manual-trigger makes VariableAvailability strict — inside this workflow every
     * `{{context.x}}` must be a declared trigger variable, so node outputs (`estimateDraft`) are never
     * referenced from another node's template here.
     */
    private function seedChildWorkflow(): Workflow
    {
        $triggerConfig = [
            'variables' => [
                ['key' => 'companyName', 'value' => 'Nordic Telecom'],
                ['key' => 'projectSummary', 'value' => 'Customer portal replacement'],
                ['key' => 'estimatedBudget', 'value' => '45000'],
                ['key' => 'dealId', 'value' => 'DEMO-1'],
            ],
            'customerContextEnabled' => false,
        ];

        $definition = [
            'trigger' => ['type' => 'manual-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => [
                [
                    'id' => 'estimation-inputs',
                    'type' => 'manual-trigger',
                    'label' => 'Estimation inputs',
                    'config' => $triggerConfig,
                    'position' => ['x' => 0, 'y' => 0],
                ],
                [
                    'id' => 'effort-breakdown',
                    'type' => 'ai-generator',
                    'label' => 'Effort breakdown',
                    'config' => [
                        'tone' => 'concise',
                        'prompt' => "Produce a phased effort breakdown for {{context.companyName}} (deal {{context.dealId}}).\n\n"
                            ."Request: {{context.projectSummary}}\nIndicative budget: {{context.estimatedBudget}}\n\n"
                            .'Use the standard Company phase model. Output a markdown table of phase, person-days and rate band, followed by the assumptions the numbers depend on.',
                        'knowledgeBaseDocuments' => [$this->documents['rateCard'], $this->documents['phaseModel']],
                        'outputVariable' => 'estimateDraft',
                    ],
                    'position' => ['x' => 260, 'y' => 0],
                ],
                [
                    'id' => 'sanity-check',
                    'type' => 'task-node',
                    'label' => 'Sanity-check the estimate',
                    'config' => [
                        'title' => 'Sanity-check the auto estimate for {{context.companyName}}',
                        'description' => 'Review the generated phase breakdown against the rate card. Adjust the effort if the model under- or over-shot, and say why.',
                        'assignTo' => (string) $this->users['cloudEngineer']->id,
                        'dueWithin' => 4,
                        'inputFields' => [
                            ['key' => 'approved', 'label' => 'Looks right', 'type' => 'checkbox', 'options' => ['Yes']],
                            ['key' => 'adjustedEffortDays', 'label' => 'Adjusted effort (person-days)', 'type' => 'number'],
                            ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
                        ],
                    ],
                    'position' => ['x' => 520, 'y' => 0],
                ],
                [
                    'id' => 'end',
                    'type' => 'termination-node',
                    'label' => 'Terminate',
                    'config' => [],
                    'position' => ['x' => 780, 'y' => 0],
                ],
            ],
            'edges' => [
                ['id' => 'ce1', 'source_node_key' => 'estimation-inputs', 'target_node_key' => 'effort-breakdown', 'branch_type' => 'default'],
                ['id' => 'ce2', 'source_node_key' => 'effort-breakdown', 'target_node_key' => 'sanity-check', 'branch_type' => 'default'],
                ['id' => 'ce3', 'source_node_key' => 'sanity-check', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];

        return $this->publishWorkflow(
            name: 'Company — Standard Estimation Pack v1',
            description: 'Reusable estimation pack: generates a phased effort breakdown and has an engineer sanity-check it. Called by the pre-sales workflow for straightforward deals.',
            team: $this->itTeam,
            createdBy: $this->users['itManager'],
            definition: $definition,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Parent workflow — "Company — Pre-Sales: RFP to Proposal" (Sales Department)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedParentWorkflow(int $childWorkflowId): Workflow
    {
        $triggerConfig = $this->rfpFormTriggerConfig();

        $definition = [
            'trigger' => ['type' => 'form-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => array_merge(
                $this->parentIntakeNodes($triggerConfig),
                $this->parentRoutingNodes($childWorkflowId),
                $this->parentProposalNodes(),
            ),
            'edges' => $this->parentEdges(),
        ];

        return $this->publishWorkflow(
            name: 'Company — Pre-Sales: RFP to Proposal',
            description: 'Inbound RFP intake through CRM capture, AI triage, complexity routing, proposal drafting and manager approval. Complex or unclassifiable deals pause for a manager to design the review path at runtime.',
            team: $this->salesTeam,
            createdBy: $this->users['salesManager'],
            definition: $definition,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rfpFormTriggerConfig(): array
    {
        return [
            'formName' => 'Solution Request',
            'description' => "Tell us about your project and we'll come back within two business days.",
            'accessLevel' => 'public',
            // Links every submission to a Customer record via email — see Modules/Customers.
            // CustomerContextVerificationRule requires the mapped form field to be `required`.
            'customerContextEnabled' => true,
            'customerContextField' => 'contactEmail',
            'formFields' => [
                ['key' => 'companyName', 'label' => 'Company name', 'type' => 'text', 'required' => true],
                ['key' => 'contactFirstName', 'label' => 'First name', 'type' => 'text', 'required' => true],
                ['key' => 'contactLastName', 'label' => 'Last name', 'type' => 'text', 'required' => true],
                ['key' => 'contactEmail', 'label' => 'Work email', 'type' => 'text', 'required' => true],
                ['key' => 'contactPhone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'industry', 'label' => 'Industry', 'type' => 'select', 'required' => true,
                    'options' => ['Banking', 'Telecom', 'Healthcare', 'Public sector', 'Retail', 'Other']],
                ['key' => 'projectSummary', 'label' => 'What do you need?', 'type' => 'textarea', 'required' => true],
                ['key' => 'estimatedBudget', 'label' => 'Indicative budget', 'type' => 'number', 'required' => true],
                ['key' => 'targetGoLive', 'label' => 'Target go-live', 'type' => 'date'],
                ['key' => 'deploymentModel', 'label' => 'Deployment', 'type' => 'select', 'required' => true,
                    'options' => ['Cloud (SaaS)', 'Hybrid', 'On-premise']],
                ['key' => 'dataSensitivity', 'label' => 'Data sensitivity', 'type' => 'select', 'required' => true,
                    'options' => ['Public', 'Internal', 'Regulated (PII / PCI / PHI)']],
                ['key' => 'integrationsNeeded', 'label' => 'Systems to integrate', 'type' => 'checkbox',
                    'options' => ['SAP', 'Salesforce', 'HubSpot', 'Custom ERP', 'None']],
            ],
        ];
    }

    /**
     * Intake and triage: capture the enquiry in the CRM, then let the classifier place it.
     *
     * @param  array<string, mixed>  $triggerConfig
     * @return list<array<string, mixed>>
     */
    private function parentIntakeNodes(array $triggerConfig): array
    {
        return [
            [
                'id' => 'rfp-intake',
                'type' => 'form-trigger',
                'label' => 'RFP intake form',
                'config' => $triggerConfig,
                'position' => ['x' => 0, 'y' => 0],
            ],
            [
                'id' => 'create-contact',
                'type' => 'hubspot-create-contact',
                'label' => 'Create contact',
                'config' => [
                    'firstName' => '{{context.contactFirstName}}',
                    'lastName' => '{{context.contactLastName}}',
                    'email' => '{{context.contactEmail}}',
                    'phone' => '{{context.contactPhone}}',
                ],
                'position' => ['x' => 260, 'y' => 0],
            ],
            [
                'id' => 'create-deal',
                'type' => 'hubspot-create-deal',
                'label' => 'Create deal',
                'config' => [
                    'dealName' => '{{context.companyName}} — {{context.industry}} solution',
                    'dealStage' => 'appointmentscheduled',
                    'pipeline' => 'default',
                    'amount' => '{{context.estimatedBudget}}',
                    'closeDate' => '{{context.targetGoLive}}',
                    // See the class docblock: the contact id created one node earlier is not currently
                    // reachable as a flat context key, so this renders empty and the association is
                    // skipped. The deal itself is still created.
                    'contactId' => '{{context.hubspotContactId}}',
                ],
                'position' => ['x' => 520, 'y' => 0],
            ],
            [
                'id' => 'score-complexity',
                'type' => 'ai-classifier',
                'label' => 'Score complexity',
                'config' => [
                    'text' => "Classify this pre-sales opportunity by delivery complexity.\n\n"
                        ."Company: {{context.companyName}}\nIndustry: {{context.industry}}\n"
                        ."Summary: {{context.projectSummary}}\nIndicative budget: {{context.estimatedBudget}}\n"
                        ."Deployment: {{context.deploymentModel}}\nData sensitivity: {{context.dataSensitivity}}\n"
                        ."Systems to integrate: {{context.integrationsNeeded}}\n\n"
                        .'Rules: an on-premise deployment or regulated data is never "simple". '
                        .'If the request depends on a domain that is absent from the capability matrix, answer "complex" — '
                        .'an unknown gets a human, not a guess.',
                    'categories' => ['simple', 'standard', 'complex'],
                    'knowledgeBaseDocuments' => [$this->documents['capabilityMatrix'], $this->documents['sowLibrary']],
                    'outputVariable' => 'triage',
                ],
                'position' => ['x' => 780, 'y' => 0],
            ],
        ];
    }

    /**
     * Routing: three lanes off a switch — two named options plus the default lane, which is the minimum
     * a switch can have and still be a switch.
     *
     * Each lane ends by writing the SAME `estimationSummary` variable. That is not stylistic:
     * DataFlowVerificationRule only permits a downstream read of a variable that is guaranteed on every
     * incoming path, so it is what lets the conditional merge declare it and the proposal generator use it.
     *
     * @return list<array<string, mixed>>
     */
    private function parentRoutingNodes(int $childWorkflowId): array
    {
        return [
            [
                'id' => 'route-by-complexity',
                'type' => 'switch',
                'label' => 'Route by complexity',
                'config' => [
                    'variable' => 'context.triage.classification',
                    'options' => ['simple', 'standard'],
                ],
                'position' => ['x' => 1040, 'y' => 0],
            ],

            // ── Lane: simple — hand it to the reusable estimation pack ──────
            [
                'id' => 'standard-estimation-pack',
                'type' => 'sub-workflow',
                'label' => 'Standard estimation pack',
                'config' => [
                    'workflowId' => (string) $childWorkflowId,
                    // Keys must match the child manual-trigger's declared variable keys exactly.
                    'inputMapping' => [
                        'companyName' => '{{context.companyName}}',
                        'projectSummary' => '{{context.projectSummary}}',
                        'estimatedBudget' => '{{context.estimatedBudget}}',
                        'dealId' => '{{context.hubspotDealId}}',
                    ],
                    'outputVariable' => 'estimationSummary',
                ],
                'position' => ['x' => 1300, 'y' => -260],
            ],

            // ── Lane: standard — an estimate and a capacity check, in parallel ──
            [
                'id' => 'fork-reviews',
                'type' => 'and-node',
                'label' => 'Fork reviews',
                'config' => [
                    'branches' => [
                        ['name' => 'Technical estimate', 'key' => 'tech'],
                        ['name' => 'Delivery capacity', 'key' => 'delivery'],
                    ],
                ],
                'position' => ['x' => 1300, 'y' => 0],
            ],
            [
                'id' => 'architect-estimate',
                'type' => 'task-node',
                'label' => 'Architect estimate',
                'config' => [
                    'title' => 'Size the solution for {{context.companyName}}',
                    'description' => "Review the RFP summary and produce an effort estimate with its assumptions. Flag anything that needs a specialist we don't have on the bench.",
                    'assignTo' => (string) $this->users['architect']->id,
                    'dueWithin' => 8,
                    'inputFields' => [
                        ['key' => 'effortDays', 'label' => 'Effort (person-days)', 'type' => 'number'],
                        ['key' => 'techStack', 'label' => 'Proposed stack', 'type' => 'textarea'],
                        ['key' => 'assumptions', 'label' => 'Assumptions', 'type' => 'textarea'],
                        ['key' => 'riskLevel', 'label' => 'Delivery risk', 'type' => 'select', 'options' => ['Low', 'Medium', 'High']],
                    ],
                ],
                'position' => ['x' => 1560, 'y' => -80],
            ],
            [
                'id' => 'capacity-check',
                'type' => 'clickup-create-task',
                'label' => 'Delivery capacity check',
                'config' => [
                    'workspaceId' => '9012345678',
                    'listId' => '901300112233',
                    'name' => 'Capacity check — {{context.companyName}} ({{context.targetGoLive}})',
                    'markdownContent' => "**Budget:** {{context.estimatedBudget}}\n**Go-live:** {{context.targetGoLive}}\n**Deployment:** {{context.deploymentModel}}\n\n"
                        .'Confirm bench availability for the estimated window and reply on this task.',
                ],
                'position' => ['x' => 1560, 'y' => 80],
            ],
            [
                'id' => 'merge-reviews',
                'type' => 'merge',
                'label' => 'Wait for both',
                'config' => ['mergeMode' => 'parallel', 'branchCount' => 2],
                'position' => ['x' => 1820, 'y' => 0],
            ],
            [
                'id' => 'consolidate-estimate',
                'type' => 'ai-generator',
                'label' => 'Consolidate estimate',
                'config' => [
                    'tone' => 'concise',
                    'prompt' => "Consolidate the pre-sales review for {{context.companyName}} into one estimate summary.\n\n"
                        ."Request: {{context.projectSummary}}\nReviewer response: {{context.task_response}}\n\n"
                        .'State the effort, the price and the delivery risk. Never invent a figure that is not above.',
                    'knowledgeBaseDocuments' => [$this->documents['rateCard']],
                    'outputVariable' => 'estimationSummary',
                ],
                'position' => ['x' => 2080, 'y' => 0],
            ],

            // ── Default lane — complex, or anything the classifier could not place.
            // This is the whole point of the demo: the platform's answer to "we don't
            // know" is the same as its answer to "this is hard" — give a human the canvas.
            [
                'id' => 'design-expert-review',
                'type' => 'dynamic-flow',
                'label' => 'Design expert review',
                'config' => [
                    'message' => 'This opportunity did not fit a standard lane. Triage: {{context.triage}}. '
                        .'Build the review path this deal actually needs.',
                    'aiSuggestion' => true,
                    'outputVariable' => 'estimationSummary',
                ],
                'position' => ['x' => 1300, 'y' => 260],
            ],

            [
                // Conditional: exactly one of the three lanes ran. branchCount is the number of
                // POSSIBLE inbound paths, not the number that will complete.
                'id' => 'merge-routes',
                'type' => 'merge',
                'label' => 'Rejoin routing lanes',
                'config' => [
                    'mergeMode' => 'conditional',
                    'branchCount' => 3,
                    'outputVariables' => ['estimationSummary'],
                ],
                'position' => ['x' => 2340, 'y' => 0],
            ],
        ];
    }

    /**
     * Proposal and approval.
     *
     * @return list<array<string, mixed>>
     */
    private function parentProposalNodes(): array
    {
        return [
            [
                'id' => 'draft-proposal',
                'type' => 'ai-generator',
                'label' => 'Draft proposal',
                'config' => [
                    'tone' => 'professional',
                    'prompt' => "Write a client-ready solution proposal for {{context.companyName}} in the {{context.industry}} sector.\n\n"
                        ."Their request: {{context.projectSummary}}\nDeployment: {{context.deploymentModel}}\n"
                        ."Data sensitivity: {{context.dataSensitivity}}\nEstimate summary: {{context.estimationSummary}}\n\n"
                        ."Sections: executive summary, proposed solution, scope and assumptions, delivery plan with phases, commercial summary, next steps.\n"
                        .'Use the proposal template structure. Never invent a price — use only the figures above.',
                    'knowledgeBaseDocuments' => [
                        $this->documents['proposalTemplate'],
                        $this->documents['rateCard'],
                        $this->documents['sowLibrary'],
                        $this->documents['securityWhitepaper'],
                    ],
                    'outputVariable' => 'proposalDraft',
                ],
                'position' => ['x' => 2600, 'y' => 0],
            ],
            [
                'id' => 'manager-approval',
                'type' => 'task-node',
                'label' => 'Manager approval',
                'config' => [
                    'title' => 'Approve proposal — {{context.companyName}}',
                    'description' => 'Review the generated proposal and the commercial terms. Approving sends it to the client.',
                    'assignTo' => (string) $this->users['salesManager']->id,
                    'dueWithin' => 8,
                    'inputFields' => [
                        ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approved', 'rejected']],
                        ['key' => 'approvedAmount', 'label' => 'Approved amount', 'type' => 'number'],
                        ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
                    ],
                ],
                'position' => ['x' => 2860, 'y' => 0],
            ],
            [
                'id' => 'approved',
                'type' => 'if-node',
                'label' => 'Approved?',
                'config' => ['conditionExpression' => 'context.task_response.decision == "approved"'],
                'position' => ['x' => 3120, 'y' => 0],
            ],
            [
                'id' => 'send-proposal',
                'type' => 'send-email',
                'label' => 'Send proposal',
                'config' => [
                    'to' => '{{context.contactEmail}}',
                    'cc' => 'presales@company.example',
                    'subject' => 'Proposal — {{context.companyName}}',
                    'bodyType' => 'html',
                    'body' => '<p>Hi {{context.contactFirstName}},</p>'
                        .'<p>Thank you for the detail you shared. Our proposal is below.</p>'
                        .'{{context.proposalDraft}}'
                        .'<p>Happy to walk through it whenever suits you.</p><p>— Pre-Sales</p>',
                ],
                'position' => ['x' => 3380, 'y' => -120],
            ],
            [
                // The rejected branch runs straight into the merge. An if-node's branches must both
                // reach the same conditional merge, but a branch is allowed to be empty — nothing is
                // sent to the client, and the instance closes on the audit trail alone.
                'id' => 'merge-approval',
                'type' => 'merge',
                'label' => 'Rejoin approval paths',
                'config' => ['mergeMode' => 'conditional', 'branchCount' => 2],
                'position' => ['x' => 3640, 'y' => 0],
            ],
            [
                'id' => 'end',
                'type' => 'termination-node',
                'label' => 'Terminate',
                'config' => [],
                'position' => ['x' => 3900, 'y' => 0],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parentEdges(): array
    {
        $edges = [
            ['rfp-intake', 'create-contact', 'default'],
            ['create-contact', 'create-deal', 'default'],
            ['create-deal', 'score-complexity', 'default'],
            ['score-complexity', 'route-by-complexity', 'default'],

            // Switch lanes. branch_type must equal the option value; the default lane is added below.
            ['route-by-complexity', 'standard-estimation-pack', 'simple'],
            ['route-by-complexity', 'fork-reviews', 'standard'],

            ['standard-estimation-pack', 'merge-routes', 'default'],
            ['architect-estimate', 'merge-reviews', 'default'],
            ['capacity-check', 'merge-reviews', 'default'],
            ['merge-reviews', 'consolidate-estimate', 'default'],
            ['consolidate-estimate', 'merge-routes', 'default'],
            ['design-expert-review', 'merge-routes', 'default'],

            ['merge-routes', 'draft-proposal', 'default'],
            ['draft-proposal', 'manager-approval', 'default'],
            ['manager-approval', 'approved', 'default'],

            // Approval split — the approved side sends, the rejected side just rejoins.
            ['approved', 'send-proposal', 'true'],
            ['approved', 'merge-approval', 'false'],
            ['send-proposal', 'merge-approval', 'default'],

            ['merge-approval', 'end', 'default'],
        ];

        $built = [];

        foreach ($edges as $index => [$source, $target, $branchType]) {
            $built[] = [
                'id' => 'e'.($index + 1),
                'source_node_key' => $source,
                'target_node_key' => $target,
                'branch_type' => $branchType,
            ];
        }

        // The default switch lane: anything not matched by a named option must still have somewhere to
        // go, or it silently dead-ends. SwitchNodeTypeRule enforces options + 1 outgoing edges for this.
        $built[] = [
            'id' => 'e-switch-default',
            'source_node_key' => 'route-by-complexity',
            'target_node_key' => 'design-expert-review',
            'branch_type' => 'default',
            'is_default_branch' => true,
        ];

        // Every outgoing edge of a fork must name the parallel merge it converges on.
        foreach ([['architect-estimate', 'tech'], ['capacity-check', 'delivery']] as $i => [$target, $branchKey]) {
            $built[] = [
                'id' => 'e-fork-'.($i + 1),
                'source_node_key' => 'fork-reviews',
                'target_node_key' => $target,
                'branch_type' => $branchKey,
                'join_node_key' => 'merge-reviews',
            ];
        }

        return $built;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Runtime sub-flow — the segment a manager composes inside `dynamic-flow`
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Stored as a tenant template rather than a workflow: it starts from `dynamic-entry` and has no
     * trigger, so it is only ever valid as a dynamic-flow SEGMENT. Seeding it gives the demo its
     * closing beat — the exception nobody predicted, saved as an asset the tenant now owns and the
     * AI suggestion can propose the next time an on-prem regulated deal arrives.
     *
     * Verified with VerificationMode::Segment, which is exactly how WorkflowDynamicFlow submissions
     * are checked (rules that need a trigger or a saved workflow are skipped).
     */
    private function seedExpertReviewTemplate(): WorkflowTemplate
    {
        $definition = [
            'nodes' => [
                [
                    'id' => 'entry',
                    'type' => 'dynamic-entry',
                    'label' => 'Entry point',
                    'config' => [],
                    'position' => ['x' => 0, 'y' => 0],
                ],
                [
                    'id' => 'fork-expert-reviews',
                    'type' => 'and-node',
                    'label' => 'Fork expert reviews',
                    'config' => [
                        'branches' => [
                            ['name' => 'Security review', 'key' => 'security'],
                            ['name' => 'Infrastructure sizing', 'key' => 'infra'],
                        ],
                    ],
                    'position' => ['x' => 240, 'y' => 0],
                ],
                [
                    'id' => 'security-review',
                    'type' => 'task-node',
                    'label' => 'On-prem security review',
                    'config' => [
                        'title' => 'On-prem security review',
                        'description' => 'Assess the on-premise deployment against our regulated-data controls. Anything that blocks the deal must be marked as a blocker, not a note.',
                        'assignTo' => (string) $this->users['itManager']->id,
                        'dueWithin' => 24,
                        'inputFields' => [
                            ['key' => 'findings', 'label' => 'Findings', 'type' => 'textarea'],
                            ['key' => 'blocker', 'label' => 'Blocking issue?', 'type' => 'select', 'options' => ['Yes', 'No']],
                        ],
                    ],
                    'position' => ['x' => 480, 'y' => -160],
                ],
                [
                    'id' => 'infrastructure-sizing',
                    'type' => 'task-node',
                    'label' => 'Infrastructure sizing',
                    'config' => [
                        'title' => 'Infrastructure sizing',
                        'description' => 'Size the on-premise footprint and estimate the monthly run cost the client will carry.',
                        'assignTo' => (string) $this->users['cloudEngineer']->id,
                        'dueWithin' => 8,
                        'inputFields' => [
                            ['key' => 'nodeCount', 'label' => 'Node count', 'type' => 'number'],
                            ['key' => 'monthlyRunCost', 'label' => 'Monthly run cost', 'type' => 'number'],
                        ],
                    ],
                    'position' => ['x' => 480, 'y' => 0],
                ],
                [
                    'id' => 'merge-expert-reviews',
                    'type' => 'merge',
                    'label' => 'Wait for both',
                    'config' => ['mergeMode' => 'parallel', 'branchCount' => 2],
                    'position' => ['x' => 720, 'y' => 0],
                ],
                [
                    'id' => 'risk-register',
                    'type' => 'ai-generator',
                    'label' => 'Risk and assumptions register',
                    'config' => [
                        'tone' => 'precise',
                        'prompt' => "Build a risk and assumptions register from the expert review responses.\n\n"
                            ."Review response: {{context.task_response}}\n\n"
                            .'List every risk with its owner and mitigation, then every assumption the estimate depends on. Do not soften a blocker into a risk.',
                        'knowledgeBaseDocuments' => [$this->documents['securityWhitepaper']],
                        'outputVariable' => 'riskRegister',
                    ],
                    'position' => ['x' => 960, 'y' => 0],
                ],
                [
                    'id' => 'end',
                    'type' => 'termination-node',
                    'label' => 'Return to parent',
                    'config' => [],
                    'position' => ['x' => 1440, 'y' => 0],
                ],
            ],
            'edges' => [
                ['id' => 'de1', 'source_node_key' => 'entry', 'target_node_key' => 'fork-expert-reviews', 'branch_type' => 'default'],
                ['id' => 'de2', 'source_node_key' => 'fork-expert-reviews', 'target_node_key' => 'security-review', 'branch_type' => 'security', 'join_node_key' => 'merge-expert-reviews'],
                ['id' => 'de3', 'source_node_key' => 'fork-expert-reviews', 'target_node_key' => 'infrastructure-sizing', 'branch_type' => 'infra', 'join_node_key' => 'merge-expert-reviews'],
                ['id' => 'de4', 'source_node_key' => 'security-review', 'target_node_key' => 'merge-expert-reviews', 'branch_type' => 'default'],
                ['id' => 'de5', 'source_node_key' => 'infrastructure-sizing', 'target_node_key' => 'merge-expert-reviews', 'branch_type' => 'default'],
                ['id' => 'de6', 'source_node_key' => 'merge-expert-reviews', 'target_node_key' => 'risk-register', 'branch_type' => 'default'],
                ['id' => 'de7', 'source_node_key' => 'risk-register', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];

        $this->assertVerifies('Company — On-Prem Expert Review', $definition, null, VerificationMode::Segment);

        return WorkflowTemplate::query()->updateOrCreate(
            ['tenant_id' => (int) $this->tenant->id, 'name' => 'Company — On-Prem Expert Review'],
            [
                'created_by_id' => (int) $this->users['salesManager']->id,
                'description' => 'The runtime sub-flow a manager composes inside the pre-sales Dynamic Flow node when a deal is on-premise or regulated: a security review and an infrastructure sizing run in parallel, then an AI risk register.',
                'category' => 'operations',
                'definition' => $definition,
                'is_active' => true,
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Persistence helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Creates (or refreshes) a workflow, verifies its definition, and publishes it as version 1 so it
     * is immediately runnable. Publishing is done directly rather than through
     * WorkflowVersioningService because that service asserts an acting user's permissions, which is
     * meaningless in a seeder — the verification step it runs is reproduced here instead.
     *
     * @param  array<string, mixed>  $definition
     */
    private function publishWorkflow(string $name, string $description, Team $team, User $createdBy, array $definition): Workflow
    {
        $workflow = Workflow::query()->firstOrNew([
            'tenant_id' => (int) $this->tenant->id,
            'name' => $name,
        ]);

        $workflow->fill([
            'team_id' => (int) $team->id,
            'created_by_id' => (int) $createdBy->id,
            'description' => $description,
            'status' => WorkflowStatus::Active,
            'draft_definition' => $definition,
            'draft_revision' => 1,
        ])->save();

        $this->assertVerifies($name, $definition, $workflow);

        $version = WorkflowVersion::query()->updateOrCreate(
            ['workflow_id' => (int) $workflow->id, 'version_number' => 1],
            [
                'tenant_id' => (int) $this->tenant->id,
                'version_label' => 'v1.0.0',
                'definition' => $definition,
                'release_note' => 'Seeded demo version.',
                'published_by_id' => (int) $createdBy->id,
                'published_at' => now(),
            ]
        );

        $workflow->forceFill([
            'current_version_id' => (int) $version->id,
            'current_version_number' => 1,
            'current_version_label' => 'v1.0.0',
        ])->save();

        return $workflow;
    }

    /**
     * Fails the seed loudly rather than persisting a definition that could never be published.
     *
     * @param  array<string, mixed>  $definition
     */
    private function assertVerifies(string $name, array $definition, ?Workflow $workflow, VerificationMode $mode = VerificationMode::Full): void
    {
        /** @var WorkflowVerificationService $verifier */
        $verifier = app(WorkflowVerificationService::class);
        $result = $verifier->verify($definition, $workflow, null, $mode)->toArray();

        // `errors`/`warnings` are flat message lists; `issues` carries the codes and node ids.
        $issues = $result['issues'] ?? [];

        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'warning') {
                $this->command?->warn(sprintf('[%s] %s: %s', $name, $issue['code'] ?? '?', $issue['message'] ?? ''));
            }
        }

        if (($result['is_publishable'] ?? false) === true) {
            return;
        }

        $errors = array_map(
            fn (array $issue): string => sprintf(
                '  - [%s] %s%s',
                $issue['code'] ?? '?',
                $issue['message'] ?? '',
                ($issue['node_id'] ?? null) !== null ? " (node: {$issue['node_id']})" : '',
            ),
            array_values(array_filter($issues, fn (array $issue): bool => ($issue['severity'] ?? '') === 'error')),
        );

        throw new RuntimeException("Demo workflow \"{$name}\" failed verification:\n".implode("\n", $errors));
    }
}
