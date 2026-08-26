<?php

namespace Database\Seeders;

use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\Demo\DemoDocumentLibrary;
use Illuminate\Database\Seeder;
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
 * the integrations/knowledge-base rows the nodes depend on, and three published workflow artifacts
 * that together exercise every seeded node type.
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
 *    incoming path. That is why all four switch lanes converge on the same `estimationSummary` variable
 *    before the proposal generator reads it.
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

        $this->seedTenant();
        $this->seedPeopleAndTeams();
        $this->seedIntegrations();
        $this->seedKnowledgeBase();

        $child = $this->seedChildWorkflow();
        $this->seedParentWorkflow((int) $child->id);
        $this->seedExpertReviewTemplate();

        $this->command?->info('Demo tenant "Company" seeded. Sign in with any @company.example address / '.self::PASSWORD);
    }

    private function seedTenant(): void
    {
        $this->tenant = Tenant::query()->updateOrCreate(
            ['business_name' => 'Company'],
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
     * Intake and triage: capture in the CRM, score the opportunity, then qualify it.
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
                'position' => ['x' => 240, 'y' => 0],
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
                'position' => ['x' => 480, 'y' => 0],
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
                        .'If the request depends on a domain that is absent from the capability matrix, answer "unclear".',
                    'categories' => ['simple', 'standard', 'complex', 'unclear'],
                    'knowledgeBaseDocuments' => [$this->documents['capabilityMatrix'], $this->documents['sowLibrary']],
                    'outputVariable' => 'triage',
                ],
                'position' => ['x' => 720, 'y' => 0],
            ],
            [
                'id' => 'extract-requirements',
                'type' => 'ai-generator',
                'label' => 'Extract requirements',
                'config' => [
                    'tone' => 'precise',
                    'prompt' => "Extract the structured requirements from this request.\n\n"
                        ."Request: {{context.projectSummary}}\nDeployment: {{context.deploymentModel}}\n"
                        ."Data sensitivity: {{context.dataSensitivity}}\nSystems to integrate: {{context.integrationsNeeded}}\n\n"
                        ."Return ONLY valid JSON, with no markdown fences and no commentary, matching this schema:\n"
                        .'{"drivers":[string],"constraints":[string],"integrations":[string],"complianceFlags":[string]}',
                    'knowledgeBaseDocuments' => [$this->documents['capabilityMatrix']],
                    'outputVariable' => 'requirementsJson',
                ],
                'position' => ['x' => 960, 'y' => 0],
            ],
            [
                // parse-json takes a BARE context key, unlike every other node, which takes Mustache.
                'id' => 'parse-requirements',
                'type' => 'parse-json',
                'label' => 'Read requirements',
                'config' => [
                    'inputVariable' => 'context.requirementsJson',
                    'outputVariable' => 'requirements',
                ],
                'position' => ['x' => 1200, 'y' => 0],
            ],
            [
                'id' => 'qualified',
                'type' => 'if-node',
                'label' => 'Qualified?',
                'config' => [
                    // Dotted paths are legal here: if/switch expressions are parsed, not interpolated.
                    'conditionExpression' => 'context.estimatedBudget >= 15000 && context.triage.confidence >= 0.6',
                ],
                'position' => ['x' => 1440, 'y' => 0],
            ],
            [
                'id' => 'polite-decline',
                'type' => 'send-email',
                'label' => 'Polite decline',
                'config' => [
                    'to' => '{{context.contactEmail}}',
                    'cc' => 'presales@company.example',
                    'subject' => 'Re: your solution request — {{context.companyName}}',
                    'bodyType' => 'html',
                    'body' => '<p>Hi {{context.contactFirstName}},</p>'
                        .'<p>Thank you for reaching out to Company about your project. Based on the scope and budget you shared, '
                        ."this isn't a strong fit for how we engage right now — but we'd genuinely like to stay in touch as the project develops.</p>"
                        .'<p>— Company Pre-Sales</p>',
                ],
                'position' => ['x' => 1680, 'y' => 320],
            ],
        ];
    }

    /**
     * Routing: four lanes off a switch. Each lane ends by writing the SAME `estimationSummary`
     * variable, which is what lets the conditional merge declare it and the proposal generator
     * downstream read it — DataFlowVerificationRule only allows a read that is guaranteed on every
     * incoming path.
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
                    'options' => ['simple', 'standard', 'complex'],
                ],
                'position' => ['x' => 1680, 'y' => 0],
            ],

            // ── Lane: simple ────────────────────────────────────────────────
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
                'position' => ['x' => 1920, 'y' => -480],
            ],

            // ── Lane: standard — three reviews in parallel ──────────────────
            [
                'id' => 'fork-reviews',
                'type' => 'and-node',
                'label' => 'Fork reviews',
                'config' => [
                    'branches' => [
                        ['name' => 'Technical estimate', 'key' => 'tech'],
                        ['name' => 'Commercial pricing', 'key' => 'commercial'],
                        ['name' => 'Delivery capacity', 'key' => 'delivery'],
                    ],
                ],
                'position' => ['x' => 1920, 'y' => -240],
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
                'position' => ['x' => 2160, 'y' => -360],
            ],
            [
                'id' => 'commercial-pricing',
                'type' => 'task-node',
                'label' => 'Commercial pricing',
                'config' => [
                    'title' => 'Price the {{context.companyName}} proposal',
                    'description' => "Apply the 2026 rate card to the architect's effort estimate. Any discount above 12% needs manager sign-off.",
                    'assignTo' => (string) $this->users['pricingAnalyst']->id,
                    'dueWithin' => 8,
                    'inputFields' => [
                        ['key' => 'listPrice', 'label' => 'List price', 'type' => 'number'],
                        ['key' => 'discountPct', 'label' => 'Discount %', 'type' => 'number'],
                        ['key' => 'paymentTerms', 'label' => 'Payment terms', 'type' => 'select',
                            'options' => ['30 days', '50/50 milestone', 'Monthly retainer']],
                    ],
                ],
                'position' => ['x' => 2160, 'y' => -240],
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
                'position' => ['x' => 2160, 'y' => -120],
            ],
            [
                'id' => 'merge-reviews',
                'type' => 'merge',
                'label' => 'Wait for all three',
                'config' => ['mergeMode' => 'parallel', 'branchCount' => 3],
                'position' => ['x' => 2400, 'y' => -240],
            ],
            [
                'id' => 'consolidate-estimate',
                'type' => 'ai-generator',
                'label' => 'Consolidate estimate',
                'config' => [
                    'tone' => 'concise',
                    'prompt' => "Consolidate the pre-sales reviews for {{context.companyName}} into one estimate summary.\n\n"
                        ."Request: {{context.projectSummary}}\nRequirements: {{context.requirements}}\n"
                        ."Reviewer response: {{context.task_response}}\n\n"
                        .'State the effort, the price, the payment terms and the delivery risk. Never invent a figure that is not above.',
                    'knowledgeBaseDocuments' => [$this->documents['rateCard']],
                    'outputVariable' => 'estimationSummary',
                ],
                'position' => ['x' => 2640, 'y' => -240],
            ],

            // ── Lane: complex — the manager designs the review path at runtime ──
            [
                'id' => 'design-expert-review',
                'type' => 'dynamic-flow',
                'label' => 'Design expert review',
                'config' => [
                    'message' => 'This opportunity was classified as complex. Triage: {{context.triage}}. '
                        .'Requirements: {{context.requirements}}. Build the review path this deal actually needs.',
                    'aiSuggestion' => true,
                    'outputVariable' => 'expertReview',
                ],
                'position' => ['x' => 1920, 'y' => 0],
            ],
            [
                'id' => 'summarize-expert-review',
                'type' => 'ai-generator',
                'label' => 'Summarize expert review',
                'config' => [
                    'tone' => 'concise',
                    'prompt' => "Turn the expert review findings for {{context.companyName}} into one estimate summary.\n\n"
                        ."Request: {{context.projectSummary}}\nRequirements: {{context.requirements}}\n"
                        ."Expert review output: {{context.expertReview}}\n\n"
                        .'Carry every blocker and assumption through verbatim — do not soften them.',
                    'knowledgeBaseDocuments' => [$this->documents['rateCard'], $this->documents['securityWhitepaper']],
                    'outputVariable' => 'estimationSummary',
                ],
                'position' => ['x' => 2400, 'y' => 0],
            ],

            // ── Lane: default — the classifier could not place this deal ────
            [
                'id' => 'manual-scoping',
                'type' => 'task-node',
                'label' => 'Manual scoping',
                'config' => [
                    'title' => 'Scope an unclassified RFP — {{context.companyName}}',
                    'description' => 'The classifier could not place this opportunity against our capability matrix. Scope it by hand and record what made it unusual, so the matrix can be updated.',
                    'assignTo' => (string) $this->users['accountExec']->id,
                    'dueWithin' => 8,
                    'inputFields' => [
                        ['key' => 'scopeNotes', 'label' => 'Scope notes', 'type' => 'textarea'],
                        ['key' => 'effortDays', 'label' => 'Rough effort (person-days)', 'type' => 'number'],
                        ['key' => 'capabilityGap', 'label' => 'Capability gap', 'type' => 'select', 'options' => ['None', 'Partial', 'Significant']],
                    ],
                ],
                'position' => ['x' => 1920, 'y' => 240],
            ],
            [
                'id' => 'draft-manual-estimate',
                'type' => 'ai-generator',
                'label' => 'Draft manual estimate',
                'config' => [
                    'tone' => 'concise',
                    'prompt' => "Turn the hand-written scoping notes for {{context.companyName}} into one estimate summary.\n\n"
                        ."Request: {{context.projectSummary}}\nScoping response: {{context.task_response}}\n\n"
                        .'Use only the figures in the scoping response.',
                    'knowledgeBaseDocuments' => [$this->documents['rateCard']],
                    'outputVariable' => 'estimationSummary',
                ],
                'position' => ['x' => 2400, 'y' => 240],
            ],

            [
                // Conditional: exactly one of the four lanes ran. branchCount is the number of
                // POSSIBLE inbound paths, not the number that will complete.
                'id' => 'merge-routes',
                'type' => 'merge',
                'label' => 'Rejoin routing lanes',
                'config' => [
                    'mergeMode' => 'conditional',
                    'branchCount' => 4,
                    'outputVariables' => ['estimationSummary'],
                ],
                'position' => ['x' => 2880, 'y' => 0],
            ],
        ];
    }

    /**
     * Proposal, approval and close-out.
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
                        ."Data sensitivity: {{context.dataSensitivity}}\nRequirements: {{context.requirements}}\n"
                        ."Estimate summary: {{context.estimationSummary}}\n\n"
                        ."Sections: executive summary, proposed solution, scope and assumptions, delivery plan with phases, commercial summary, next steps.\n"
                        .'Use the Company proposal template structure. Never invent a price — use only the figures above.',
                    'knowledgeBaseDocuments' => [
                        $this->documents['proposalTemplate'],
                        $this->documents['rateCard'],
                        $this->documents['sowLibrary'],
                        $this->documents['securityWhitepaper'],
                    ],
                    'outputVariable' => 'proposalDraft',
                ],
                'position' => ['x' => 3120, 'y' => 0],
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
                        ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approved', 'revise', 'rejected']],
                        ['key' => 'approvedAmount', 'label' => 'Approved amount', 'type' => 'number'],
                        ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
                    ],
                ],
                'position' => ['x' => 3360, 'y' => 0],
            ],
            [
                'id' => 'approved',
                'type' => 'if-node',
                'label' => 'Approved?',
                'config' => ['conditionExpression' => 'context.task_response.decision == "approved"'],
                'position' => ['x' => 3600, 'y' => 0],
            ],
            [
                'id' => 'send-proposal',
                'type' => 'send-email',
                'label' => 'Send proposal',
                'config' => [
                    'to' => '{{context.contactEmail}}',
                    'cc' => 'presales@company.example',
                    'bcc' => 'crm@company.example',
                    'subject' => 'Company proposal — {{context.companyName}}',
                    'bodyType' => 'html',
                    'body' => '<p>Hi {{context.contactFirstName}},</p>'
                        .'<p>Thank you for the detail you shared. Our proposal is below.</p>'
                        .'{{context.proposalDraft}}'
                        .'<p>Happy to walk through it whenever suits you.</p><p>— Company Pre-Sales</p>',
                ],
                'position' => ['x' => 3840, 'y' => -160],
            ],
            [
                'id' => 'pre-kickoff',
                'type' => 'clickup-create-task',
                'label' => 'Pre-kickoff task',
                'config' => [
                    'workspaceId' => '9012345678',
                    'listId' => '901300445566',
                    'name' => 'Pre-kickoff — {{context.companyName}}',
                    'markdownContent' => "Proposal sent. Target go-live: {{context.targetGoLive}}.\n\n"
                        .'Prepare the kickoff deck and a provisional team allocation.',
                ],
                'position' => ['x' => 4080, 'y' => -160],
            ],
            [
                'id' => 'rework-notice',
                'type' => 'send-email',
                'label' => 'Rework notice',
                'config' => [
                    'to' => 'presales-lead@company.example',
                    'subject' => 'Proposal returned — {{context.companyName}}',
                    'bodyType' => 'text',
                    'body' => "The manager did not approve this proposal.\n\nReviewer response: {{context.task_response}}\n\n"
                        .'The draft is stored in the instance context under proposalDraft.',
                ],
                'position' => ['x' => 3840, 'y' => 160],
            ],
            [
                'id' => 'merge-approval',
                'type' => 'merge',
                'label' => 'Rejoin approval paths',
                'config' => ['mergeMode' => 'conditional', 'branchCount' => 2],
                'position' => ['x' => 4320, 'y' => 0],
            ],
            [
                'id' => 'merge-qualification',
                'type' => 'merge',
                'label' => 'Rejoin qualification paths',
                'config' => ['mergeMode' => 'conditional', 'branchCount' => 2],
                'position' => ['x' => 4560, 'y' => 0],
            ],
            [
                'id' => 'end',
                'type' => 'termination-node',
                'label' => 'Terminate',
                'config' => [],
                'position' => ['x' => 4800, 'y' => 0],
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
            ['score-complexity', 'extract-requirements', 'default'],
            ['extract-requirements', 'parse-requirements', 'default'],
            ['parse-requirements', 'qualified', 'default'],

            // Qualification split — both sides rejoin at merge-qualification.
            ['qualified', 'route-by-complexity', 'true'],
            ['qualified', 'polite-decline', 'false'],
            ['polite-decline', 'merge-qualification', 'default'],

            // Switch lanes. branch_type must equal the option value; the fourth edge is the default.
            ['route-by-complexity', 'standard-estimation-pack', 'simple'],
            ['route-by-complexity', 'fork-reviews', 'standard'],
            ['route-by-complexity', 'design-expert-review', 'complex'],

            ['standard-estimation-pack', 'merge-routes', 'default'],
            ['architect-estimate', 'merge-reviews', 'default'],
            ['commercial-pricing', 'merge-reviews', 'default'],
            ['capacity-check', 'merge-reviews', 'default'],
            ['merge-reviews', 'consolidate-estimate', 'default'],
            ['consolidate-estimate', 'merge-routes', 'default'],
            ['design-expert-review', 'summarize-expert-review', 'default'],
            ['summarize-expert-review', 'merge-routes', 'default'],
            ['manual-scoping', 'draft-manual-estimate', 'default'],
            ['draft-manual-estimate', 'merge-routes', 'default'],

            ['merge-routes', 'draft-proposal', 'default'],
            ['draft-proposal', 'manager-approval', 'default'],
            ['manager-approval', 'approved', 'default'],

            // Approval split — both sides rejoin at merge-approval.
            ['approved', 'send-proposal', 'true'],
            ['approved', 'rework-notice', 'false'],
            ['send-proposal', 'pre-kickoff', 'default'],
            ['pre-kickoff', 'merge-approval', 'default'],
            ['rework-notice', 'merge-approval', 'default'],

            ['merge-approval', 'merge-qualification', 'default'],
            ['merge-qualification', 'end', 'default'],
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

        // The default switch lane: an unclassifiable deal must still have somewhere to go, or it
        // silently dead-ends. SwitchNodeTypeRule enforces options + 1 outgoing edges for this reason.
        $built[] = [
            'id' => 'e-switch-default',
            'source_node_key' => 'route-by-complexity',
            'target_node_key' => 'manual-scoping',
            'branch_type' => 'default',
            'is_default_branch' => true,
        ];

        // Every outgoing edge of a fork must name the parallel merge it converges on.
        foreach ([['architect-estimate', 'tech'], ['commercial-pricing', 'commercial'], ['capacity-check', 'delivery']] as $i => [$target, $branchKey]) {
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
                            ['name' => 'Legal review', 'key' => 'legal'],
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
                    'id' => 'legal-review',
                    'type' => 'task-node',
                    'label' => 'Legal review of on-prem terms',
                    'config' => [
                        'title' => 'Legal review of on-prem terms',
                        'description' => 'Review liability, data residency and support terms for an on-premise regulated deployment.',
                        'assignTo' => (string) $this->users['owner']->id,
                        'dueWithin' => 24,
                        'inputFields' => [
                            ['key' => 'contractNotes', 'label' => 'Contract notes', 'type' => 'textarea'],
                        ],
                    ],
                    'position' => ['x' => 480, 'y' => 160],
                ],
                [
                    'id' => 'merge-expert-reviews',
                    'type' => 'merge',
                    'label' => 'Wait for all three',
                    'config' => ['mergeMode' => 'parallel', 'branchCount' => 3],
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
                        'outputVariable' => 'riskRegister',
                    ],
                    'position' => ['x' => 960, 'y' => 0],
                ],
                [
                    'id' => 'lead-confirms',
                    'type' => 'task-node',
                    'label' => 'Presales lead confirms',
                    'config' => [
                        'title' => 'Presales lead confirms the expert review',
                        'description' => 'Confirm the review is complete and the deal can proceed to proposal.',
                        'assignTo' => (string) $this->users['salesManager']->id,
                        'dueWithin' => 4,
                        'inputFields' => [
                            ['key' => 'proceed', 'label' => 'Proceed?', 'type' => 'select', 'options' => ['Proceed', 'Hold', 'Withdraw']],
                            ['key' => 'summary', 'label' => 'Summary for the proposal', 'type' => 'textarea'],
                        ],
                    ],
                    'position' => ['x' => 1200, 'y' => 0],
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
                ['id' => 'de4', 'source_node_key' => 'fork-expert-reviews', 'target_node_key' => 'legal-review', 'branch_type' => 'legal', 'join_node_key' => 'merge-expert-reviews'],
                ['id' => 'de5', 'source_node_key' => 'security-review', 'target_node_key' => 'merge-expert-reviews', 'branch_type' => 'default'],
                ['id' => 'de6', 'source_node_key' => 'infrastructure-sizing', 'target_node_key' => 'merge-expert-reviews', 'branch_type' => 'default'],
                ['id' => 'de7', 'source_node_key' => 'legal-review', 'target_node_key' => 'merge-expert-reviews', 'branch_type' => 'default'],
                ['id' => 'de8', 'source_node_key' => 'merge-expert-reviews', 'target_node_key' => 'risk-register', 'branch_type' => 'default'],
                ['id' => 'de9', 'source_node_key' => 'risk-register', 'target_node_key' => 'lead-confirms', 'branch_type' => 'default'],
                ['id' => 'de10', 'source_node_key' => 'lead-confirms', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];

        $this->assertVerifies('Company — On-Prem Expert Review', $definition, null, VerificationMode::Segment);

        return WorkflowTemplate::query()->updateOrCreate(
            ['tenant_id' => (int) $this->tenant->id, 'name' => 'Company — On-Prem Expert Review'],
            [
                'created_by_id' => (int) $this->users['salesManager']->id,
                'description' => 'The runtime sub-flow a manager composes inside the pre-sales Dynamic Flow node when a deal is on-premise and regulated: three parallel expert reviews, an AI risk register, and a lead confirmation.',
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
