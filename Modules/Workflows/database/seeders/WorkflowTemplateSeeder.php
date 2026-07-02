<?php

namespace Modules\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workflows\Models\WorkflowTemplate;

/**
 * Seeds the 7 starter workflow templates (global, tenant_id null) covering common HR, finance, and
 * operations automation scenarios. Every definition here is verified to pass
 * WorkflowVerificationService::verify() with zero errors before being persisted.
 *
 * Two simplifications relative to the source scenarios, forced by current platform capabilities:
 *  - There is no generic "automatic system action" node type (only send-email and task-node exist
 *    under the action category), so steps like "update leave balance" or "generate PDF" are modeled
 *    as task-node placeholders titled "System: ...". These currently require a human to complete them
 *    in the task inbox rather than running automatically.
 *  - There is no seeded 'webhook-trigger' node type, so the two externally-triggered scenarios
 *    (vendor invoice, crisis management) start from a form-trigger instead.
 */
class WorkflowTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['name' => 'Leave Request Approval', 'category' => 'hr', 'description' => 'Employee submits a leave request; balance is checked automatically, and the manager approves or rejects it.', 'definition' => $this->definition1()],
            ['name' => 'Multi-Level Procurement Approval', 'category' => 'finance', 'description' => 'Routes a procurement request through manager, department head, or committee approval based on level, then generates a PO.', 'definition' => $this->definition2()],
            ['name' => 'Employee Onboarding', 'category' => 'hr', 'description' => 'Runs IT, HR, and manager onboarding tasks in parallel, then creates the employee record and sends a welcome email.', 'definition' => $this->definition3()],
            ['name' => 'Employee Offboarding', 'category' => 'hr', 'description' => 'Runs IT, admin, finance, and manager offboarding tasks in parallel, then generates an offboarding report.', 'definition' => $this->definition4()],
            ['name' => 'Internal Support Ticket', 'category' => 'operations', 'description' => 'Routes a support ticket to the right department and escalates to a supervisor if unresolved.', 'definition' => $this->definition5()],
            ['name' => 'Vendor Invoice Approval', 'category' => 'finance', 'description' => 'Validates a supplier invoice against its Purchase Order, routes it for finance approval, and schedules payment.', 'definition' => $this->definition6()],
            ['name' => 'Enterprise Crisis Management', 'category' => 'operations', 'description' => 'Coordinates parallel crisis response teams, reviews containment, and closes out with a post-incident report.', 'definition' => $this->definition7()],
        ];

        foreach ($templates as $template) {
            WorkflowTemplate::query()->updateOrCreate(
                ['tenant_id' => null, 'name' => $template['name']],
                [
                    'created_by_id' => null,
                    'description' => $template['description'],
                    'category' => $template['category'],
                    'definition' => $template['definition'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function definition1(): array
    {
        $triggerConfig = [
            'formName' => 'Leave Request Form',
            'description' => 'Submit a leave request for approval.',
            'formFields' => [
                ['key' => 'employeeEmail', 'label' => 'Employee Email', 'type' => 'text'],
                ['key' => 'managerEmail', 'label' => 'Manager Email', 'type' => 'text'],
                ['key' => 'hrEmail', 'label' => 'HR Email', 'type' => 'text'],
                ['key' => 'leaveType', 'label' => 'Leave Type', 'type' => 'select', 'options' => ['Annual', 'Sick', 'Unpaid']],
                ['key' => 'startDate', 'label' => 'Start Date', 'type' => 'date'],
                ['key' => 'endDate', 'label' => 'End Date', 'type' => 'date'],
                ['key' => 'requestedDays', 'label' => 'Requested Days', 'type' => 'number'],
                ['key' => 'availableBalance', 'label' => 'Available Leave Balance (days)', 'type' => 'number'],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'textarea'],
            ],
            'accessLevel' => 'tenant',
        ];

        return [
            'trigger' => ['type' => 'form-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => [
                ['id' => 'trigger-leave-request', 'type' => 'form-trigger', 'label' => 'Leave Request Submitted', 'config' => $triggerConfig, 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'send-confirmation', 'type' => 'send-email', 'label' => 'Send Confirmation', 'config' => [
                    'to' => '{{context.employeeEmail}}',
                    'subject' => 'We received your leave request',
                    'bodyType' => 'text',
                    'body' => "Hi, we've received your leave request. We'll notify you once it's reviewed.",
                ], 'position' => ['x' => 240, 'y' => 0]],
                ['id' => 'if-balance-check', 'type' => 'if-node', 'label' => 'Balance Sufficient?', 'config' => [
                    'conditionExpression' => 'context.requestedDays > context.availableBalance',
                ], 'position' => ['x' => 480, 'y' => 0]],
                ['id' => 'send-balance-rejection', 'type' => 'send-email', 'label' => 'Send Balance Rejection', 'config' => [
                    'to' => '{{context.employeeEmail}}',
                    'subject' => 'Leave Request Rejected - Insufficient Balance',
                    'bodyType' => 'text',
                    'body' => 'Unfortunately your requested leave exceeds your available balance. Please contact HR if you have questions.',
                ], 'position' => ['x' => 720, 'y' => -160]],
                ['id' => 'send-manager-request', 'type' => 'send-email', 'label' => 'Send Approval Request', 'config' => [
                    'to' => '{{context.managerEmail}}',
                    'subject' => 'Leave Approval Needed',
                    'bodyType' => 'text',
                    'body' => 'An employee has requested leave. Please review and respond in your task inbox.',
                ], 'position' => ['x' => 720, 'y' => 120]],
                ['id' => 'task-manager-review', 'type' => 'task-node', 'label' => 'Manager Reviews Request', 'config' => [
                    'title' => 'Review Leave Request',
                    'description' => "Approve or reject the employee's leave request.",
                    'assignTo' => '1',
                    'dueWithin' => 48,
                    'inputFields' => [
                        ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approved', 'rejected']],
                        ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
                    ],
                ], 'position' => ['x' => 960, 'y' => 120]],
                ['id' => 'if-manager-decision', 'type' => 'if-node', 'label' => 'Manager Decision?', 'config' => [
                    'conditionExpression' => 'context.task_response.decision == "approved"',
                ], 'position' => ['x' => 1200, 'y' => 120]],
                ['id' => 'task-update-balance', 'type' => 'task-node', 'label' => 'System: Update Leave Balance', 'config' => [
                    'title' => 'System: Update Leave Balance',
                    'description' => "Placeholder for an automated system action - update the employee's leave balance in the HR system.",
                    'assignTo' => '1',
                    'dueWithin' => 1,
                    'inputFields' => [
                        ['key' => 'confirmed', 'label' => 'Balance Updated', 'type' => 'checkbox', 'options' => ['done']],
                    ],
                ], 'position' => ['x' => 1440, 'y' => 40]],
                ['id' => 'send-approval-notice', 'type' => 'send-email', 'label' => 'Notify Employee & HR', 'config' => [
                    'to' => '{{context.employeeEmail}}',
                    'cc' => '{{context.hrEmail}}',
                    'subject' => 'Leave Request Approved',
                    'bodyType' => 'text',
                    'body' => 'Your leave request has been approved. Your updated leave balance will reflect this shortly.',
                ], 'position' => ['x' => 1680, 'y' => 40]],
                ['id' => 'send-manager-rejection', 'type' => 'send-email', 'label' => 'Notify Employee of Rejection', 'config' => [
                    'to' => '{{context.employeeEmail}}',
                    'subject' => 'Leave Request Rejected',
                    'bodyType' => 'text',
                    'body' => 'Your manager has reviewed and declined your leave request. Please reach out to your manager directly for details.',
                ], 'position' => ['x' => 1440, 'y' => 240]],
                ['id' => 'merge-decision-outcome', 'type' => 'merge', 'label' => 'Merge Decision Outcome', 'config' => [
                    'mergeMode' => 'conditional',
                    'branchCount' => 2,
                ], 'position' => ['x' => 1920, 'y' => 140]],
                ['id' => 'merge-balance-outcome', 'type' => 'merge', 'label' => 'Merge Balance Outcome', 'config' => [
                    'mergeMode' => 'conditional',
                    'branchCount' => 2,
                ], 'position' => ['x' => 2160, 'y' => 0]],
                ['id' => 'end', 'type' => 'termination-node', 'label' => 'Terminate', 'config' => [], 'position' => ['x' => 2400, 'y' => 0]],
            ],
            'edges' => [
                ['id' => 'e1', 'source_node_key' => 'trigger-leave-request', 'target_node_key' => 'send-confirmation', 'branch_type' => 'default'],
                ['id' => 'e2', 'source_node_key' => 'send-confirmation', 'target_node_key' => 'if-balance-check', 'branch_type' => 'default'],
                ['id' => 'e3', 'source_node_key' => 'if-balance-check', 'target_node_key' => 'send-balance-rejection', 'branch_type' => 'true'],
                ['id' => 'e4', 'source_node_key' => 'if-balance-check', 'target_node_key' => 'send-manager-request', 'branch_type' => 'false'],
                ['id' => 'e5', 'source_node_key' => 'send-balance-rejection', 'target_node_key' => 'merge-balance-outcome', 'branch_type' => 'default'],
                ['id' => 'e6', 'source_node_key' => 'send-manager-request', 'target_node_key' => 'task-manager-review', 'branch_type' => 'default'],
                ['id' => 'e7', 'source_node_key' => 'task-manager-review', 'target_node_key' => 'if-manager-decision', 'branch_type' => 'default'],
                ['id' => 'e8', 'source_node_key' => 'if-manager-decision', 'target_node_key' => 'task-update-balance', 'branch_type' => 'true'],
                ['id' => 'e9', 'source_node_key' => 'if-manager-decision', 'target_node_key' => 'send-manager-rejection', 'branch_type' => 'false'],
                ['id' => 'e10', 'source_node_key' => 'task-update-balance', 'target_node_key' => 'send-approval-notice', 'branch_type' => 'default'],
                ['id' => 'e11', 'source_node_key' => 'send-approval-notice', 'target_node_key' => 'merge-decision-outcome', 'branch_type' => 'default'],
                ['id' => 'e12', 'source_node_key' => 'send-manager-rejection', 'target_node_key' => 'merge-decision-outcome', 'branch_type' => 'default'],
                ['id' => 'e13', 'source_node_key' => 'merge-decision-outcome', 'target_node_key' => 'merge-balance-outcome', 'branch_type' => 'default'],
                ['id' => 'e14', 'source_node_key' => 'merge-balance-outcome', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];
    }

    private function definition2(): array
    {
        $triggerConfig = [
            'formName' => 'Procurement Request Form',
            'description' => 'Submit a procurement request for approval.',
            'formFields' => [
                ['key' => 'requesterEmail', 'label' => 'Requester Email', 'type' => 'text'],
                ['key' => 'itemDescription', 'label' => 'Item Description', 'type' => 'textarea'],
                ['key' => 'supplierName', 'label' => 'Supplier Name', 'type' => 'text'],
                ['key' => 'supplierEmail', 'label' => 'Supplier Email', 'type' => 'text'],
                ['key' => 'estimatedCost', 'label' => 'Estimated Cost', 'type' => 'number'],
                ['key' => 'businessJustification', 'label' => 'Business Justification', 'type' => 'textarea'],
                ['key' => 'requiredDeliveryDate', 'label' => 'Required Delivery Date', 'type' => 'date'],
                [
                    'key' => 'approvalLevel', 'label' => 'Approval Level', 'type' => 'select',
                    'options' => ['level1', 'level2', 'committee'],
                ],
            ],
            'accessLevel' => 'tenant',
        ];

        return [
            'trigger' => ['type' => 'form-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => [
                ['id' => 'trigger-procurement', 'type' => 'form-trigger', 'label' => 'Procurement Request Submitted', 'config' => $triggerConfig, 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'send-confirmation', 'type' => 'send-email', 'label' => 'Send Confirmation', 'config' => [
                    'to' => '{{context.requesterEmail}}',
                    'subject' => 'Procurement request received',
                    'bodyType' => 'text',
                    'body' => 'Your procurement request has entered the approval process. We will notify you of the outcome.',
                ], 'position' => ['x' => 240, 'y' => 0]],
                ['id' => 'switch-approval-level', 'type' => 'switch', 'label' => 'Determine Approval Level', 'config' => [
                    'variable' => 'context.approvalLevel',
                    'options' => ['level1', 'level2', 'committee'],
                ], 'position' => ['x' => 480, 'y' => 0]],

                ['id' => 'task-manager-approval-l1', 'type' => 'task-node', 'label' => 'Direct Manager Approval', 'config' => [
                    'title' => 'Direct Manager Approval', 'assignTo' => '1', 'dueWithin' => 48,
                    'inputFields' => [
                        ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approved', 'rejected', 'more_info']],
                        ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
                    ],
                ], 'position' => ['x' => 720, 'y' => -240]],

                ['id' => 'task-manager-approval-l2', 'type' => 'task-node', 'label' => 'Manager Approval (Level 2)', 'config' => [
                    'title' => 'Manager Approval', 'assignTo' => '1', 'dueWithin' => 48,
                    'inputFields' => [
                        ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approved', 'rejected', 'more_info']],
                        ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
                    ],
                ], 'position' => ['x' => 720, 'y' => -80]],
                ['id' => 'task-depthead-approval', 'type' => 'task-node', 'label' => 'Department Head Approval', 'config' => [
                    'title' => 'Department Head Approval', 'assignTo' => '1', 'dueWithin' => 48,
                    'inputFields' => [
                        ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approved', 'rejected', 'more_info']],
                        ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
                    ],
                ], 'position' => ['x' => 960, 'y' => -80]],

                ['id' => 'task-committee-approval', 'type' => 'task-node', 'label' => 'Procurement Committee Approval', 'config' => [
                    'title' => 'Procurement Committee Approval', 'assignTo' => '1', 'dueWithin' => 72,
                    'inputFields' => [
                        ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approved', 'rejected', 'more_info']],
                        ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
                    ],
                ], 'position' => ['x' => 720, 'y' => 80]],

                ['id' => 'send-invalid-level', 'type' => 'send-email', 'label' => 'Notify Configuration Issue', 'config' => [
                    'to' => '{{context.requesterEmail}}',
                    'subject' => 'Procurement request needs attention',
                    'bodyType' => 'text',
                    'body' => 'Your request could not be routed automatically. The procurement team has been notified.',
                ], 'position' => ['x' => 720, 'y' => 240]],

                ['id' => 'merge-approval-review', 'type' => 'merge', 'label' => 'Merge Approval Review', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 4,
                ], 'position' => ['x' => 1200, 'y' => 0]],

                ['id' => 'switch-approval-decision', 'type' => 'switch', 'label' => 'Approval Decision?', 'config' => [
                    'variable' => 'context.task_response.decision',
                    'options' => ['approved', 'rejected', 'more_info'],
                ], 'position' => ['x' => 1440, 'y' => 0]],

                ['id' => 'action-generate-po', 'type' => 'task-node', 'label' => 'System: Generate Purchase Order (PDF)', 'config' => [
                    'title' => 'System: Generate Purchase Order (PDF)',
                    'description' => 'Placeholder for an automated system action - generate the PO document.',
                    'assignTo' => '1', 'dueWithin' => 1,
                    'inputFields' => [
                        ['key' => 'confirmed', 'label' => 'PO Generated', 'type' => 'checkbox', 'options' => ['done']],
                    ],
                ], 'position' => ['x' => 1680, 'y' => -160]],
                ['id' => 'send-po-supplier', 'type' => 'send-email', 'label' => 'Send PO to Supplier', 'config' => [
                    'to' => '{{context.supplierEmail}}',
                    'subject' => 'Purchase Order',
                    'bodyType' => 'text',
                    'body' => 'Please find attached your Purchase Order for the approved procurement request.',
                ], 'position' => ['x' => 1920, 'y' => -160]],

                ['id' => 'send-rejection-notice', 'type' => 'send-email', 'label' => 'Notify Rejection', 'config' => [
                    'to' => '{{context.requesterEmail}}',
                    'subject' => 'Procurement request rejected',
                    'bodyType' => 'text',
                    'body' => 'Your procurement request was rejected. Please see the reviewer comments in your task history for details.',
                ], 'position' => ['x' => 1680, 'y' => 0]],

                ['id' => 'send-more-info-request', 'type' => 'send-email', 'label' => 'Request Additional Information', 'config' => [
                    'to' => '{{context.requesterEmail}}',
                    'subject' => 'More information needed',
                    'bodyType' => 'text',
                    'body' => 'The reviewer has requested additional information for your procurement request. Please see your task history for details.',
                ], 'position' => ['x' => 1680, 'y' => 160]],

                ['id' => 'send-decision-error', 'type' => 'send-email', 'label' => 'Notify Decision Error', 'config' => [
                    'to' => '{{context.requesterEmail}}',
                    'subject' => 'Procurement request needs attention',
                    'bodyType' => 'text',
                    'body' => 'We could not determine a decision for your request. The procurement team has been notified.',
                ], 'position' => ['x' => 1680, 'y' => 320]],

                ['id' => 'merge-decision-outcome', 'type' => 'merge', 'label' => 'Merge Decision Outcome', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 4,
                ], 'position' => ['x' => 2160, 'y' => 0]],

                ['id' => 'end', 'type' => 'termination-node', 'label' => 'Terminate', 'config' => [], 'position' => ['x' => 2400, 'y' => 0]],
            ],
            'edges' => [
                ['id' => 'e1', 'source_node_key' => 'trigger-procurement', 'target_node_key' => 'send-confirmation', 'branch_type' => 'default'],
                ['id' => 'e2', 'source_node_key' => 'send-confirmation', 'target_node_key' => 'switch-approval-level', 'branch_type' => 'default'],

                ['id' => 'e3', 'source_node_key' => 'switch-approval-level', 'target_node_key' => 'task-manager-approval-l1', 'branch_type' => 'level1'],
                ['id' => 'e4', 'source_node_key' => 'switch-approval-level', 'target_node_key' => 'task-manager-approval-l2', 'branch_type' => 'level2'],
                ['id' => 'e5', 'source_node_key' => 'switch-approval-level', 'target_node_key' => 'task-committee-approval', 'branch_type' => 'committee'],
                ['id' => 'e6', 'source_node_key' => 'switch-approval-level', 'target_node_key' => 'send-invalid-level', 'branch_type' => 'default'],

                ['id' => 'e7', 'source_node_key' => 'task-manager-approval-l1', 'target_node_key' => 'merge-approval-review', 'branch_type' => 'default'],
                ['id' => 'e8', 'source_node_key' => 'task-manager-approval-l2', 'target_node_key' => 'task-depthead-approval', 'branch_type' => 'default'],
                ['id' => 'e9', 'source_node_key' => 'task-depthead-approval', 'target_node_key' => 'merge-approval-review', 'branch_type' => 'default'],
                ['id' => 'e10', 'source_node_key' => 'task-committee-approval', 'target_node_key' => 'merge-approval-review', 'branch_type' => 'default'],
                ['id' => 'e11', 'source_node_key' => 'send-invalid-level', 'target_node_key' => 'merge-approval-review', 'branch_type' => 'default'],

                ['id' => 'e12', 'source_node_key' => 'merge-approval-review', 'target_node_key' => 'switch-approval-decision', 'branch_type' => 'default'],

                ['id' => 'e13', 'source_node_key' => 'switch-approval-decision', 'target_node_key' => 'action-generate-po', 'branch_type' => 'approved'],
                ['id' => 'e14', 'source_node_key' => 'switch-approval-decision', 'target_node_key' => 'send-rejection-notice', 'branch_type' => 'rejected'],
                ['id' => 'e15', 'source_node_key' => 'switch-approval-decision', 'target_node_key' => 'send-more-info-request', 'branch_type' => 'more_info'],
                ['id' => 'e16', 'source_node_key' => 'switch-approval-decision', 'target_node_key' => 'send-decision-error', 'branch_type' => 'default'],

                ['id' => 'e17', 'source_node_key' => 'action-generate-po', 'target_node_key' => 'send-po-supplier', 'branch_type' => 'default'],
                ['id' => 'e18', 'source_node_key' => 'send-po-supplier', 'target_node_key' => 'merge-decision-outcome', 'branch_type' => 'default'],
                ['id' => 'e19', 'source_node_key' => 'send-rejection-notice', 'target_node_key' => 'merge-decision-outcome', 'branch_type' => 'default'],
                ['id' => 'e20', 'source_node_key' => 'send-more-info-request', 'target_node_key' => 'merge-decision-outcome', 'branch_type' => 'default'],
                ['id' => 'e21', 'source_node_key' => 'send-decision-error', 'target_node_key' => 'merge-decision-outcome', 'branch_type' => 'default'],

                ['id' => 'e22', 'source_node_key' => 'merge-decision-outcome', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];
    }

    private function definition3(): array
    {
        $triggerConfig = [
            'formName' => 'Employee Onboarding Form',
            'description' => 'HR submits a new employee record to start onboarding.',
            'formFields' => [
                ['key' => 'employeeName', 'label' => 'Employee Name', 'type' => 'text'],
                ['key' => 'employeeEmail', 'label' => 'Employee Email', 'type' => 'text'],
                ['key' => 'department', 'label' => 'Department', 'type' => 'text'],
                ['key' => 'jobTitle', 'label' => 'Job Title', 'type' => 'text'],
                ['key' => 'startDate', 'label' => 'Start Date', 'type' => 'date'],
                ['key' => 'managerEmail', 'label' => 'Manager Email', 'type' => 'text'],
                ['key' => 'itContactEmail', 'label' => 'IT Contact Email', 'type' => 'text'],
                ['key' => 'hrOpsEmail', 'label' => 'HR Operations Email', 'type' => 'text'],
            ],
            'accessLevel' => 'tenant',
        ];

        return [
            'trigger' => ['type' => 'form-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => [
                ['id' => 'trigger-onboarding', 'type' => 'form-trigger', 'label' => 'Onboarding Info Submitted', 'config' => $triggerConfig, 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'if-validate-fields', 'type' => 'if-node', 'label' => 'Validate Required Fields', 'config' => [
                    'conditionExpression' => 'context.employeeName != "" && context.employeeEmail != ""',
                ], 'position' => ['x' => 240, 'y' => 0]],

                ['id' => 'send-validation-error', 'type' => 'send-email', 'label' => 'Notify Incomplete Submission', 'config' => [
                    'to' => '{{context.hrOpsEmail}}',
                    'subject' => 'Onboarding request incomplete',
                    'bodyType' => 'text',
                    'body' => 'The onboarding submission is missing required employee information. Please review and resubmit.',
                ], 'position' => ['x' => 480, 'y' => -240]],

                ['id' => 'and-node-onboarding', 'type' => 'and-node', 'label' => 'Fork Onboarding Tasks', 'config' => [
                    'branches' => [
                        ['name' => 'IT Setup', 'key' => 'it'],
                        ['name' => 'HR Documentation', 'key' => 'hr'],
                        ['name' => 'Manager Onboarding', 'key' => 'manager'],
                    ],
                ], 'position' => ['x' => 480, 'y' => 80]],

                ['id' => 'task-it-setup', 'type' => 'task-node', 'label' => 'IT Setup', 'config' => [
                    'title' => 'IT Setup', 'description' => "Prepare the employee's accounts, devices, and system access.",
                    'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => [['key' => 'completed', 'label' => 'Setup Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 720, 'y' => -40]],
                ['id' => 'task-hr-documentation', 'type' => 'task-node', 'label' => 'HR Documentation', 'config' => [
                    'title' => 'HR Documentation', 'description' => 'Prepare employment documents, payroll information, and personnel records.',
                    'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => [['key' => 'completed', 'label' => 'Documentation Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 720, 'y' => 80]],
                ['id' => 'task-manager-onboarding', 'type' => 'task-node', 'label' => 'Manager Onboarding Prep', 'config' => [
                    'title' => 'Manager Onboarding Prep', 'description' => 'Prepare the onboarding schedule, welcome session, and initial work plan.',
                    'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => [['key' => 'completed', 'label' => 'Prep Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 720, 'y' => 200]],

                ['id' => 'merge-onboarding-tasks', 'type' => 'merge', 'label' => 'Wait for All Departments', 'config' => [
                    'mergeMode' => 'parallel', 'branchCount' => 3,
                ], 'position' => ['x' => 960, 'y' => 80]],

                ['id' => 'action-create-record', 'type' => 'task-node', 'label' => 'System: Create Employee Record', 'config' => [
                    'title' => 'System: Create Employee Record',
                    'description' => "Placeholder for an automated system action - create the employee's official record.",
                    'assignTo' => '1', 'dueWithin' => 1,
                    'inputFields' => [['key' => 'confirmed', 'label' => 'Record Created', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 1200, 'y' => 80]],

                ['id' => 'send-welcome-email', 'type' => 'send-email', 'label' => 'Send Welcome Email', 'config' => [
                    'to' => '{{context.employeeEmail}}',
                    'subject' => 'Welcome aboard!',
                    'bodyType' => 'text',
                    'body' => 'Welcome to the team! This email contains your onboarding instructions and the employee handbook.',
                ], 'position' => ['x' => 1440, 'y' => 80]],

                ['id' => 'merge-validation-outcome', 'type' => 'merge', 'label' => 'Merge Validation Outcome', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 2,
                ], 'position' => ['x' => 1680, 'y' => -80]],

                ['id' => 'end', 'type' => 'termination-node', 'label' => 'Terminate', 'config' => [], 'position' => ['x' => 1920, 'y' => -80]],
            ],
            'edges' => [
                ['id' => 'e1', 'source_node_key' => 'trigger-onboarding', 'target_node_key' => 'if-validate-fields', 'branch_type' => 'default'],
                ['id' => 'e2', 'source_node_key' => 'if-validate-fields', 'target_node_key' => 'and-node-onboarding', 'branch_type' => 'true'],
                ['id' => 'e3', 'source_node_key' => 'if-validate-fields', 'target_node_key' => 'send-validation-error', 'branch_type' => 'false'],

                ['id' => 'e4', 'source_node_key' => 'and-node-onboarding', 'target_node_key' => 'task-it-setup', 'branch_type' => 'it', 'join_node_key' => 'merge-onboarding-tasks'],
                ['id' => 'e5', 'source_node_key' => 'and-node-onboarding', 'target_node_key' => 'task-hr-documentation', 'branch_type' => 'hr', 'join_node_key' => 'merge-onboarding-tasks'],
                ['id' => 'e6', 'source_node_key' => 'and-node-onboarding', 'target_node_key' => 'task-manager-onboarding', 'branch_type' => 'manager', 'join_node_key' => 'merge-onboarding-tasks'],

                ['id' => 'e7', 'source_node_key' => 'task-it-setup', 'target_node_key' => 'merge-onboarding-tasks', 'branch_type' => 'default'],
                ['id' => 'e8', 'source_node_key' => 'task-hr-documentation', 'target_node_key' => 'merge-onboarding-tasks', 'branch_type' => 'default'],
                ['id' => 'e9', 'source_node_key' => 'task-manager-onboarding', 'target_node_key' => 'merge-onboarding-tasks', 'branch_type' => 'default'],

                ['id' => 'e10', 'source_node_key' => 'merge-onboarding-tasks', 'target_node_key' => 'action-create-record', 'branch_type' => 'default'],
                ['id' => 'e11', 'source_node_key' => 'action-create-record', 'target_node_key' => 'send-welcome-email', 'branch_type' => 'default'],
                ['id' => 'e12', 'source_node_key' => 'send-welcome-email', 'target_node_key' => 'merge-validation-outcome', 'branch_type' => 'default'],
                ['id' => 'e13', 'source_node_key' => 'send-validation-error', 'target_node_key' => 'merge-validation-outcome', 'branch_type' => 'default'],

                ['id' => 'e14', 'source_node_key' => 'merge-validation-outcome', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];
    }

    private function definition4(): array
    {
        $triggerConfig = [
            'variables' => [
                ['key' => 'employeeName', 'value' => 'Jane Doe'],
                ['key' => 'employeeEmail', 'value' => 'jane.doe@example.com'],
                ['key' => 'department', 'value' => 'Engineering'],
                ['key' => 'lastWorkingDay', 'value' => '2026-08-01'],
                ['key' => 'hrEmail', 'value' => 'hr@example.com'],
                ['key' => 'itEmail', 'value' => 'it@example.com'],
                ['key' => 'financeEmail', 'value' => 'finance@example.com'],
                ['key' => 'adminEmail', 'value' => 'admin@example.com'],
            ],
        ];

        return [
            'trigger' => ['type' => 'manual-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => [
                ['id' => 'trigger-offboarding', 'type' => 'manual-trigger', 'label' => 'Start Offboarding', 'config' => $triggerConfig, 'position' => ['x' => 0, 'y' => 0]],

                ['id' => 'and-node-offboarding', 'type' => 'and-node', 'label' => 'Fork Offboarding Tasks', 'config' => [
                    'branches' => [
                        ['name' => 'IT Access Revocation', 'key' => 'it'],
                        ['name' => 'Asset Recovery', 'key' => 'admin'],
                        ['name' => 'Finance Settlement', 'key' => 'finance'],
                        ['name' => 'Knowledge Transfer', 'key' => 'manager'],
                    ],
                ], 'position' => ['x' => 240, 'y' => 0]],

                ['id' => 'task-it-revocation', 'type' => 'task-node', 'label' => 'IT Access Revocation', 'config' => [
                    'title' => 'IT Access Revocation', 'description' => 'Revoke all accounts and system access.',
                    'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => [['key' => 'completed', 'label' => 'Revocation Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 480, 'y' => -240]],
                ['id' => 'task-asset-recovery', 'type' => 'task-node', 'label' => 'Asset Recovery', 'config' => [
                    'title' => 'Asset Recovery', 'description' => 'Collect company assets such as laptops and access cards.',
                    'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => [['key' => 'completed', 'label' => 'Recovery Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 480, 'y' => -80]],
                ['id' => 'task-finance-settlement', 'type' => 'task-node', 'label' => 'Finance Settlement', 'config' => [
                    'title' => 'Finance Settlement', 'description' => "Complete the employee's financial settlement.",
                    'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => [['key' => 'completed', 'label' => 'Settlement Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 480, 'y' => 80]],
                ['id' => 'task-knowledge-transfer', 'type' => 'task-node', 'label' => 'Knowledge Transfer', 'config' => [
                    'title' => 'Knowledge Transfer', 'description' => 'Oversee knowledge transfer and project handover.',
                    'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => [['key' => 'completed', 'label' => 'Handover Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 480, 'y' => 240]],

                ['id' => 'merge-offboarding-tasks', 'type' => 'merge', 'label' => 'Wait for All Departments', 'config' => [
                    'mergeMode' => 'parallel', 'branchCount' => 4,
                ], 'position' => ['x' => 720, 'y' => 0]],

                ['id' => 'action-generate-report', 'type' => 'task-node', 'label' => 'System: Generate Offboarding Report', 'config' => [
                    'title' => 'System: Generate Offboarding Report',
                    'description' => "Placeholder for an automated system action - generate the offboarding report and archive the employee's records.",
                    'assignTo' => '1', 'dueWithin' => 1,
                    'inputFields' => [['key' => 'confirmed', 'label' => 'Report Generated', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 960, 'y' => 0]],

                ['id' => 'send-notify-hr', 'type' => 'send-email', 'label' => 'Notify HR', 'config' => [
                    'to' => '{{context.hrEmail}}',
                    'subject' => 'Offboarding complete',
                    'bodyType' => 'text',
                    'body' => 'All offboarding tasks have been completed. The offboarding report and full audit trail are attached.',
                ], 'position' => ['x' => 1200, 'y' => 0]],

                ['id' => 'end', 'type' => 'termination-node', 'label' => 'Terminate', 'config' => [], 'position' => ['x' => 1440, 'y' => 0]],
            ],
            'edges' => [
                ['id' => 'e1', 'source_node_key' => 'trigger-offboarding', 'target_node_key' => 'and-node-offboarding', 'branch_type' => 'default'],

                ['id' => 'e2', 'source_node_key' => 'and-node-offboarding', 'target_node_key' => 'task-it-revocation', 'branch_type' => 'it', 'join_node_key' => 'merge-offboarding-tasks'],
                ['id' => 'e3', 'source_node_key' => 'and-node-offboarding', 'target_node_key' => 'task-asset-recovery', 'branch_type' => 'admin', 'join_node_key' => 'merge-offboarding-tasks'],
                ['id' => 'e4', 'source_node_key' => 'and-node-offboarding', 'target_node_key' => 'task-finance-settlement', 'branch_type' => 'finance', 'join_node_key' => 'merge-offboarding-tasks'],
                ['id' => 'e5', 'source_node_key' => 'and-node-offboarding', 'target_node_key' => 'task-knowledge-transfer', 'branch_type' => 'manager', 'join_node_key' => 'merge-offboarding-tasks'],

                ['id' => 'e6', 'source_node_key' => 'task-it-revocation', 'target_node_key' => 'merge-offboarding-tasks', 'branch_type' => 'default'],
                ['id' => 'e7', 'source_node_key' => 'task-asset-recovery', 'target_node_key' => 'merge-offboarding-tasks', 'branch_type' => 'default'],
                ['id' => 'e8', 'source_node_key' => 'task-finance-settlement', 'target_node_key' => 'merge-offboarding-tasks', 'branch_type' => 'default'],
                ['id' => 'e9', 'source_node_key' => 'task-knowledge-transfer', 'target_node_key' => 'merge-offboarding-tasks', 'branch_type' => 'default'],

                ['id' => 'e10', 'source_node_key' => 'merge-offboarding-tasks', 'target_node_key' => 'action-generate-report', 'branch_type' => 'default'],
                ['id' => 'e11', 'source_node_key' => 'action-generate-report', 'target_node_key' => 'send-notify-hr', 'branch_type' => 'default'],
                ['id' => 'e12', 'source_node_key' => 'send-notify-hr', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];
    }

    private function definition5(): array
    {
        $triggerConfig = [
            'formName' => 'Support Ticket Form',
            'description' => 'Create an internal support ticket.',
            'formFields' => [
                ['key' => 'requesterEmail', 'label' => 'Requester Email', 'type' => 'text'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => ['IT', 'HR', 'Finance', 'Facilities']],
                ['key' => 'priority', 'label' => 'Priority', 'type' => 'select', 'options' => ['Low', 'Medium', 'High', 'Critical']],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ],
            'accessLevel' => 'tenant',
        ];

        $resolutionFields = [
            ['key' => 'resolved', 'label' => 'Resolved?', 'type' => 'select', 'options' => ['yes', 'no']],
            ['key' => 'resolutionNotes', 'label' => 'Resolution Notes', 'type' => 'textarea'],
        ];

        return [
            'trigger' => ['type' => 'form-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => [
                ['id' => 'trigger-support-ticket', 'type' => 'form-trigger', 'label' => 'Support Ticket Created', 'config' => $triggerConfig, 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'send-confirmation', 'type' => 'send-email', 'label' => 'Send Ticket Confirmation', 'config' => [
                    'to' => '{{context.requesterEmail}}',
                    'subject' => 'We received your support ticket',
                    'bodyType' => 'text',
                    'body' => "We've received your support ticket and will route it to the right team shortly.",
                ], 'position' => ['x' => 240, 'y' => 0]],
                ['id' => 'switch-route-category', 'type' => 'switch', 'label' => 'Route by Category', 'config' => [
                    'variable' => 'context.category',
                    'options' => ['IT', 'HR', 'Finance', 'Facilities'],
                ], 'position' => ['x' => 480, 'y' => 0]],

                ['id' => 'task-it-team', 'type' => 'task-node', 'label' => 'IT Team', 'config' => [
                    'title' => 'Resolve Support Ticket (IT)', 'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => $resolutionFields,
                ], 'position' => ['x' => 720, 'y' => -320]],
                ['id' => 'task-hr-team', 'type' => 'task-node', 'label' => 'HR Team', 'config' => [
                    'title' => 'Resolve Support Ticket (HR)', 'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => $resolutionFields,
                ], 'position' => ['x' => 720, 'y' => -160]],
                ['id' => 'task-finance-team', 'type' => 'task-node', 'label' => 'Finance Team', 'config' => [
                    'title' => 'Resolve Support Ticket (Finance)', 'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => $resolutionFields,
                ], 'position' => ['x' => 720, 'y' => 0]],
                ['id' => 'task-facilities-team', 'type' => 'task-node', 'label' => 'Facilities Team', 'config' => [
                    'title' => 'Resolve Support Ticket (Facilities)', 'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => $resolutionFields,
                ], 'position' => ['x' => 720, 'y' => 160]],
                ['id' => 'task-general-team', 'type' => 'task-node', 'label' => 'General Support Team', 'config' => [
                    'title' => 'Resolve Support Ticket (General)', 'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => $resolutionFields,
                ], 'position' => ['x' => 720, 'y' => 320]],

                ['id' => 'merge-ticket-routed', 'type' => 'merge', 'label' => 'Merge Routed Ticket', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 5,
                ], 'position' => ['x' => 960, 'y' => 0]],

                ['id' => 'if-issue-resolved', 'type' => 'if-node', 'label' => 'Issue Resolved?', 'config' => [
                    'conditionExpression' => 'context.task_response.resolved == "yes"',
                ], 'position' => ['x' => 1200, 'y' => 0]],

                ['id' => 'send-resolution-notice', 'type' => 'send-email', 'label' => 'Notify Requester', 'config' => [
                    'to' => '{{context.requesterEmail}}',
                    'subject' => 'Your support ticket has been resolved',
                    'bodyType' => 'text',
                    'body' => 'Your support ticket has been resolved. See your ticket history for the resolution details.',
                ], 'position' => ['x' => 1440, 'y' => -160]],
                ['id' => 'action-update-records', 'type' => 'task-node', 'label' => 'System: Update Support Records', 'config' => [
                    'title' => 'System: Update Support Records',
                    'description' => 'Placeholder for an automated system action - record the resolution time and close the ticket.',
                    'assignTo' => '1', 'dueWithin' => 1,
                    'inputFields' => [['key' => 'confirmed', 'label' => 'Records Updated', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 1680, 'y' => -160]],

                ['id' => 'task-escalate-supervisor', 'type' => 'task-node', 'label' => 'Escalate to Supervisor', 'config' => [
                    'title' => 'Escalate to Supervisor', 'description' => 'Further action is needed to resolve this ticket.',
                    'assignTo' => '1', 'dueWithin' => 24,
                    'inputFields' => [['key' => 'notes', 'label' => 'Escalation Notes', 'type' => 'textarea']],
                ], 'position' => ['x' => 1440, 'y' => 160]],

                ['id' => 'merge-resolution-outcome', 'type' => 'merge', 'label' => 'Merge Resolution Outcome', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 2,
                ], 'position' => ['x' => 1920, 'y' => 0]],

                ['id' => 'end', 'type' => 'termination-node', 'label' => 'Terminate', 'config' => [], 'position' => ['x' => 2160, 'y' => 0]],
            ],
            'edges' => [
                ['id' => 'e1', 'source_node_key' => 'trigger-support-ticket', 'target_node_key' => 'send-confirmation', 'branch_type' => 'default'],
                ['id' => 'e2', 'source_node_key' => 'send-confirmation', 'target_node_key' => 'switch-route-category', 'branch_type' => 'default'],

                ['id' => 'e3', 'source_node_key' => 'switch-route-category', 'target_node_key' => 'task-it-team', 'branch_type' => 'IT'],
                ['id' => 'e4', 'source_node_key' => 'switch-route-category', 'target_node_key' => 'task-hr-team', 'branch_type' => 'HR'],
                ['id' => 'e5', 'source_node_key' => 'switch-route-category', 'target_node_key' => 'task-finance-team', 'branch_type' => 'Finance'],
                ['id' => 'e6', 'source_node_key' => 'switch-route-category', 'target_node_key' => 'task-facilities-team', 'branch_type' => 'Facilities'],
                ['id' => 'e7', 'source_node_key' => 'switch-route-category', 'target_node_key' => 'task-general-team', 'branch_type' => 'default'],

                ['id' => 'e8', 'source_node_key' => 'task-it-team', 'target_node_key' => 'merge-ticket-routed', 'branch_type' => 'default'],
                ['id' => 'e9', 'source_node_key' => 'task-hr-team', 'target_node_key' => 'merge-ticket-routed', 'branch_type' => 'default'],
                ['id' => 'e10', 'source_node_key' => 'task-finance-team', 'target_node_key' => 'merge-ticket-routed', 'branch_type' => 'default'],
                ['id' => 'e11', 'source_node_key' => 'task-facilities-team', 'target_node_key' => 'merge-ticket-routed', 'branch_type' => 'default'],
                ['id' => 'e12', 'source_node_key' => 'task-general-team', 'target_node_key' => 'merge-ticket-routed', 'branch_type' => 'default'],

                ['id' => 'e13', 'source_node_key' => 'merge-ticket-routed', 'target_node_key' => 'if-issue-resolved', 'branch_type' => 'default'],

                ['id' => 'e14', 'source_node_key' => 'if-issue-resolved', 'target_node_key' => 'send-resolution-notice', 'branch_type' => 'true'],
                ['id' => 'e15', 'source_node_key' => 'if-issue-resolved', 'target_node_key' => 'task-escalate-supervisor', 'branch_type' => 'false'],

                ['id' => 'e16', 'source_node_key' => 'send-resolution-notice', 'target_node_key' => 'action-update-records', 'branch_type' => 'default'],
                ['id' => 'e17', 'source_node_key' => 'action-update-records', 'target_node_key' => 'merge-resolution-outcome', 'branch_type' => 'default'],
                ['id' => 'e18', 'source_node_key' => 'task-escalate-supervisor', 'target_node_key' => 'merge-resolution-outcome', 'branch_type' => 'default'],

                ['id' => 'e19', 'source_node_key' => 'merge-resolution-outcome', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];
    }

    private function definition6(): array
    {
        $triggerConfig = [
            'formName' => 'Vendor Invoice Intake',
            'description' => 'Receives a supplier invoice (e.g. submitted by an integration or portal) to begin approval.',
            'formFields' => [
                ['key' => 'invoiceNumber', 'label' => 'Invoice Number', 'type' => 'text'],
                ['key' => 'supplierName', 'label' => 'Supplier Name', 'type' => 'text'],
                ['key' => 'supplierEmail', 'label' => 'Supplier Email', 'type' => 'text'],
                ['key' => 'invoiceAmount', 'label' => 'Invoice Amount', 'type' => 'number'],
                ['key' => 'poNumber', 'label' => 'Purchase Order Number', 'type' => 'text'],
                ['key' => 'poAmount', 'label' => 'Purchase Order Amount', 'type' => 'number'],
                ['key' => 'financeEmail', 'label' => 'Finance Team Email', 'type' => 'text'],
            ],
            'accessLevel' => 'public',
        ];

        return [
            'trigger' => ['type' => 'form-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => [
                ['id' => 'trigger-invoice', 'type' => 'form-trigger', 'label' => 'Supplier Invoice Received', 'config' => $triggerConfig, 'position' => ['x' => 0, 'y' => 0]],

                ['id' => 'if-validate-invoice', 'type' => 'if-node', 'label' => 'Invoice Matches PO?', 'config' => [
                    'conditionExpression' => 'context.invoiceAmount == context.poAmount',
                ], 'position' => ['x' => 240, 'y' => 0]],

                ['id' => 'send-notify-finance-discrepancy', 'type' => 'send-email', 'label' => 'Notify Finance of Discrepancy', 'config' => [
                    'to' => '{{context.financeEmail}}',
                    'subject' => 'Invoice discrepancy detected',
                    'bodyType' => 'text',
                    'body' => 'An incoming supplier invoice does not match its Purchase Order and needs manual review.',
                ], 'position' => ['x' => 480, 'y' => -200]],

                ['id' => 'task-finance-approval', 'type' => 'task-node', 'label' => 'Finance Approval', 'config' => [
                    'title' => 'Approve Vendor Invoice', 'assignTo' => '1', 'dueWithin' => 48,
                    'inputFields' => [
                        ['key' => 'decision', 'label' => 'Decision', 'type' => 'select', 'options' => ['approved', 'rejected']],
                        ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea'],
                    ],
                ], 'position' => ['x' => 480, 'y' => 80]],
                ['id' => 'if-approval-decision', 'type' => 'if-node', 'label' => 'Invoice Approved?', 'config' => [
                    'conditionExpression' => 'context.task_response.decision == "approved"',
                ], 'position' => ['x' => 720, 'y' => 80]],

                ['id' => 'action-schedule-payment', 'type' => 'task-node', 'label' => 'System: Schedule Payment', 'config' => [
                    'title' => 'System: Schedule Payment',
                    'description' => 'Placeholder for an automated system action - schedule payment in the financial system.',
                    'assignTo' => '1', 'dueWithin' => 1,
                    'inputFields' => [['key' => 'confirmed', 'label' => 'Payment Scheduled', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 960, 'y' => 0]],
                ['id' => 'send-notify-supplier', 'type' => 'send-email', 'label' => 'Notify Supplier', 'config' => [
                    'to' => '{{context.supplierEmail}}',
                    'subject' => 'Payment scheduled',
                    'bodyType' => 'text',
                    'body' => 'Your invoice has been approved and payment has been scheduled.',
                ], 'position' => ['x' => 1200, 'y' => 0]],

                ['id' => 'send-request-corrected-invoice', 'type' => 'send-email', 'label' => 'Request Corrected Invoice', 'config' => [
                    'to' => '{{context.supplierEmail}}',
                    'subject' => 'Invoice requires correction',
                    'bodyType' => 'text',
                    'body' => 'Your invoice was rejected during review. Please submit a corrected invoice.',
                ], 'position' => ['x' => 960, 'y' => 200]],

                ['id' => 'merge-approval-outcome', 'type' => 'merge', 'label' => 'Merge Approval Outcome', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 2,
                ], 'position' => ['x' => 1440, 'y' => 80]],

                ['id' => 'merge-validation-outcome', 'type' => 'merge', 'label' => 'Merge Validation Outcome', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 2,
                ], 'position' => ['x' => 1680, 'y' => -60]],

                ['id' => 'end', 'type' => 'termination-node', 'label' => 'Terminate', 'config' => [], 'position' => ['x' => 1920, 'y' => -60]],
            ],
            'edges' => [
                ['id' => 'e1', 'source_node_key' => 'trigger-invoice', 'target_node_key' => 'if-validate-invoice', 'branch_type' => 'default'],

                ['id' => 'e2', 'source_node_key' => 'if-validate-invoice', 'target_node_key' => 'task-finance-approval', 'branch_type' => 'true'],
                ['id' => 'e3', 'source_node_key' => 'if-validate-invoice', 'target_node_key' => 'send-notify-finance-discrepancy', 'branch_type' => 'false'],

                ['id' => 'e4', 'source_node_key' => 'task-finance-approval', 'target_node_key' => 'if-approval-decision', 'branch_type' => 'default'],
                ['id' => 'e5', 'source_node_key' => 'if-approval-decision', 'target_node_key' => 'action-schedule-payment', 'branch_type' => 'true'],
                ['id' => 'e6', 'source_node_key' => 'if-approval-decision', 'target_node_key' => 'send-request-corrected-invoice', 'branch_type' => 'false'],

                ['id' => 'e7', 'source_node_key' => 'action-schedule-payment', 'target_node_key' => 'send-notify-supplier', 'branch_type' => 'default'],
                ['id' => 'e8', 'source_node_key' => 'send-notify-supplier', 'target_node_key' => 'merge-approval-outcome', 'branch_type' => 'default'],
                ['id' => 'e9', 'source_node_key' => 'send-request-corrected-invoice', 'target_node_key' => 'merge-approval-outcome', 'branch_type' => 'default'],

                ['id' => 'e10', 'source_node_key' => 'merge-approval-outcome', 'target_node_key' => 'merge-validation-outcome', 'branch_type' => 'default'],
                ['id' => 'e11', 'source_node_key' => 'send-notify-finance-discrepancy', 'target_node_key' => 'merge-validation-outcome', 'branch_type' => 'default'],

                ['id' => 'e12', 'source_node_key' => 'merge-validation-outcome', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];
    }

    private function definition7(): array
    {
        $triggerConfig = [
            'formName' => 'Crisis Report Intake',
            'description' => 'Receives a crisis report (e.g. from a monitoring system or manually submitted) to begin the response workflow.',
            'formFields' => [
                ['key' => 'crisisDescription', 'label' => 'Crisis Description', 'type' => 'textarea'],
                ['key' => 'crisisType', 'label' => 'Crisis Type', 'type' => 'select', 'options' => ['security', 'natural_disaster', 'operational', 'reputational']],
                ['key' => 'severityLevel', 'label' => 'Severity Level', 'type' => 'select', 'options' => ['low', 'medium', 'high', 'critical']],
                ['key' => 'reporterEmail', 'label' => 'Reporter Email', 'type' => 'text'],
                ['key' => 'crisisTeamEmail', 'label' => 'Crisis Team Email', 'type' => 'text'],
                ['key' => 'executiveEmail', 'label' => 'Executive Team Email', 'type' => 'text'],
                ['key' => 'stakeholderEmail', 'label' => 'Stakeholder Distribution Email', 'type' => 'text'],
            ],
            'accessLevel' => 'public',
        ];

        return [
            'trigger' => ['type' => 'form-trigger', 'config' => $triggerConfig],
            'variables' => [],
            'settings' => [],
            'nodes' => [
                ['id' => 'trigger-crisis', 'type' => 'form-trigger', 'label' => 'Crisis Detected', 'config' => $triggerConfig, 'position' => ['x' => 0, 'y' => 0]],

                ['id' => 'action-emergency-notifications', 'type' => 'task-node', 'label' => 'System: Send Emergency Notifications', 'config' => [
                    'title' => 'System: Send Emergency Notifications',
                    'description' => 'Placeholder for an automated system action - broadcast emergency notifications via email and other channels.',
                    'assignTo' => '1', 'dueWithin' => 1,
                    'inputFields' => [['key' => 'confirmed', 'label' => 'Notifications Sent', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 240, 'y' => 0]],
                ['id' => 'send-notify-crisis-team', 'type' => 'send-email', 'label' => 'Notify Crisis Team', 'config' => [
                    'to' => '{{context.crisisTeamEmail}}',
                    'subject' => 'Crisis reported - response required',
                    'bodyType' => 'text',
                    'body' => 'A crisis has been reported: {{context.crisisType}}. Please review and begin coordinated response.',
                ], 'position' => ['x' => 480, 'y' => 0]],

                ['id' => 'if-severity-critical', 'type' => 'if-node', 'label' => 'Severity Critical or High?', 'config' => [
                    'conditionExpression' => 'context.severityLevel == "critical" || context.severityLevel == "high"',
                ], 'position' => ['x' => 720, 'y' => 0]],
                ['id' => 'send-escalate-executives', 'type' => 'send-email', 'label' => 'Escalate to Executives', 'config' => [
                    'to' => '{{context.executiveEmail}}',
                    'subject' => 'Critical crisis escalation',
                    'bodyType' => 'text',
                    'body' => 'A critical or high severity crisis has been reported and requires executive attention.',
                ], 'position' => ['x' => 960, 'y' => -160]],
                ['id' => 'merge-severity-outcome', 'type' => 'merge', 'label' => 'Merge Severity Outcome', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 2,
                ], 'position' => ['x' => 1200, 'y' => 0]],

                ['id' => 'task-assign-commander', 'type' => 'task-node', 'label' => 'Assign Crisis Commander', 'config' => [
                    'title' => 'Assign Crisis Commander', 'description' => 'Assign a crisis commander to coordinate the response.',
                    'assignTo' => '1', 'dueWithin' => 1,
                    'inputFields' => [['key' => 'commanderConfirmed', 'label' => 'Commander Assigned', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 1440, 'y' => 0]],

                ['id' => 'and-node-response', 'type' => 'and-node', 'label' => 'Fork Response Teams', 'config' => [
                    'branches' => [
                        ['name' => 'Containment Team', 'key' => 'containment'],
                        ['name' => 'Communications Team', 'key' => 'communications'],
                        ['name' => 'Operations Documentation', 'key' => 'documentation'],
                        ['name' => 'Legal Review', 'key' => 'legal'],
                        ['name' => 'Public Statement', 'key' => 'public_statement'],
                    ],
                ], 'position' => ['x' => 1680, 'y' => 0]],

                ['id' => 'task-containment-team', 'type' => 'task-node', 'label' => 'Containment Team', 'config' => [
                    'title' => 'Containment Team', 'description' => 'Work to contain the incident.',
                    'assignTo' => '1', 'dueWithin' => 8,
                    'inputFields' => [['key' => 'completed', 'label' => 'Containment Actions Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 1920, 'y' => -320]],
                ['id' => 'task-communications-team', 'type' => 'task-node', 'label' => 'Communications Team', 'config' => [
                    'title' => 'Communications Team', 'description' => 'Manage external communications.',
                    'assignTo' => '1', 'dueWithin' => 8,
                    'inputFields' => [['key' => 'completed', 'label' => 'Communications Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 1920, 'y' => -160]],
                ['id' => 'task-operations-documentation', 'type' => 'task-node', 'label' => 'Operations Documentation', 'config' => [
                    'title' => 'Operations Documentation', 'description' => 'Document ongoing activities.',
                    'assignTo' => '1', 'dueWithin' => 8,
                    'inputFields' => [['key' => 'completed', 'label' => 'Documentation Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 1920, 'y' => 0]],
                ['id' => 'task-legal-review', 'type' => 'task-node', 'label' => 'Legal Review', 'config' => [
                    'title' => 'Legal Review', 'description' => 'Perform legal assessments where required.',
                    'assignTo' => '1', 'dueWithin' => 8,
                    'inputFields' => [['key' => 'completed', 'label' => 'Legal Review Completed', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 1920, 'y' => 160]],
                ['id' => 'send-public-statement', 'type' => 'send-email', 'label' => 'Send Public Statement', 'config' => [
                    'to' => '{{context.stakeholderEmail}}',
                    'subject' => 'Public statement regarding ongoing incident',
                    'bodyType' => 'text',
                    'body' => 'We are aware of an ongoing incident and are actively working to resolve it. Updates will follow.',
                ], 'position' => ['x' => 1920, 'y' => 320]],

                ['id' => 'merge-response-teams', 'type' => 'merge', 'label' => 'Wait for All Response Teams', 'config' => [
                    'mergeMode' => 'parallel', 'branchCount' => 5,
                ], 'position' => ['x' => 2160, 'y' => 0]],

                ['id' => 'task-crisis-review-meeting', 'type' => 'task-node', 'label' => 'Crisis Review Meeting', 'config' => [
                    'title' => 'Crisis Review Meeting', 'description' => 'Evaluate whether the crisis has been successfully contained.',
                    'assignTo' => '1', 'dueWithin' => 4,
                    'inputFields' => [
                        ['key' => 'contained', 'label' => 'Contained?', 'type' => 'select', 'options' => ['yes', 'no']],
                        ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
                    ],
                ], 'position' => ['x' => 2400, 'y' => 0]],

                ['id' => 'if-crisis-contained', 'type' => 'if-node', 'label' => 'Crisis Contained?', 'config' => [
                    'conditionExpression' => 'context.task_response.contained == "yes"',
                ], 'position' => ['x' => 2640, 'y' => 0]],

                ['id' => 'send-continue-response-notice', 'type' => 'send-email', 'label' => 'Notify Response Continues', 'config' => [
                    'to' => '{{context.crisisTeamEmail}}',
                    'subject' => 'Containment not yet achieved',
                    'bodyType' => 'text',
                    'body' => 'Containment has not yet been achieved. Please continue response efforts and re-run the review once ready.',
                ], 'position' => ['x' => 2880, 'y' => -160]],

                ['id' => 'and-node-closure', 'type' => 'and-node', 'label' => 'Fork Closure Tasks', 'config' => [
                    'branches' => [
                        ['name' => 'Post-Incident Report', 'key' => 'report'],
                        ['name' => 'Update Crisis Policies', 'key' => 'policies'],
                    ],
                ], 'position' => ['x' => 2880, 'y' => 160]],
                ['id' => 'task-post-incident-report', 'type' => 'task-node', 'label' => 'Post-Incident Report', 'config' => [
                    'title' => 'Post-Incident Report', 'description' => 'Prepare the post-incident report covering timeline, impact, and actions taken.',
                    'assignTo' => '1', 'dueWithin' => 72,
                    'inputFields' => [['key' => 'completed', 'label' => 'Report Drafted', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 3120, 'y' => 80]],
                ['id' => 'task-update-crisis-policies', 'type' => 'task-node', 'label' => 'Update Crisis Policies', 'config' => [
                    'title' => 'Update Crisis Policies', 'description' => 'Update crisis policies based on recommendations from this incident.',
                    'assignTo' => '1', 'dueWithin' => 72,
                    'inputFields' => [['key' => 'completed', 'label' => 'Policies Updated', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 3120, 'y' => 240]],

                ['id' => 'merge-closure-tasks', 'type' => 'merge', 'label' => 'Merge Closure Tasks', 'config' => [
                    'mergeMode' => 'parallel', 'branchCount' => 2,
                ], 'position' => ['x' => 3360, 'y' => 160]],

                ['id' => 'action-generate-crisis-report', 'type' => 'task-node', 'label' => 'System: Generate Crisis Report', 'config' => [
                    'title' => 'System: Generate Crisis Report',
                    'description' => 'Placeholder for an automated system action - compile the comprehensive post-incident report.',
                    'assignTo' => '1', 'dueWithin' => 1,
                    'inputFields' => [['key' => 'confirmed', 'label' => 'Report Generated', 'type' => 'checkbox', 'options' => ['done']]],
                ], 'position' => ['x' => 3600, 'y' => 160]],
                ['id' => 'send-report-executives', 'type' => 'send-email', 'label' => 'Send Report to Executives', 'config' => [
                    'to' => '{{context.executiveEmail}}',
                    'subject' => 'Post-incident report',
                    'bodyType' => 'text',
                    'body' => 'The comprehensive post-incident report is attached, including timeline, impact, and recommendations.',
                ], 'position' => ['x' => 3840, 'y' => 160]],
                ['id' => 'send-notify-stakeholders', 'type' => 'send-email', 'label' => 'Notify All Stakeholders', 'config' => [
                    'to' => '{{context.stakeholderEmail}}',
                    'subject' => 'Incident closure notification',
                    'bodyType' => 'text',
                    'body' => 'The incident has been resolved and closed. Thank you for your patience.',
                ], 'position' => ['x' => 4080, 'y' => 160]],

                ['id' => 'merge-containment-outcome', 'type' => 'merge', 'label' => 'Merge Containment Outcome', 'config' => [
                    'mergeMode' => 'conditional', 'branchCount' => 2,
                ], 'position' => ['x' => 4320, 'y' => 0]],

                ['id' => 'end', 'type' => 'termination-node', 'label' => 'Terminate', 'config' => [], 'position' => ['x' => 4560, 'y' => 0]],
            ],
            'edges' => [
                ['id' => 'e1', 'source_node_key' => 'trigger-crisis', 'target_node_key' => 'action-emergency-notifications', 'branch_type' => 'default'],
                ['id' => 'e2', 'source_node_key' => 'action-emergency-notifications', 'target_node_key' => 'send-notify-crisis-team', 'branch_type' => 'default'],
                ['id' => 'e3', 'source_node_key' => 'send-notify-crisis-team', 'target_node_key' => 'if-severity-critical', 'branch_type' => 'default'],

                ['id' => 'e4', 'source_node_key' => 'if-severity-critical', 'target_node_key' => 'send-escalate-executives', 'branch_type' => 'true'],
                ['id' => 'e5', 'source_node_key' => 'if-severity-critical', 'target_node_key' => 'merge-severity-outcome', 'branch_type' => 'false'],
                ['id' => 'e6', 'source_node_key' => 'send-escalate-executives', 'target_node_key' => 'merge-severity-outcome', 'branch_type' => 'default'],

                ['id' => 'e7', 'source_node_key' => 'merge-severity-outcome', 'target_node_key' => 'task-assign-commander', 'branch_type' => 'default'],
                ['id' => 'e8', 'source_node_key' => 'task-assign-commander', 'target_node_key' => 'and-node-response', 'branch_type' => 'default'],

                ['id' => 'e9', 'source_node_key' => 'and-node-response', 'target_node_key' => 'task-containment-team', 'branch_type' => 'containment', 'join_node_key' => 'merge-response-teams'],
                ['id' => 'e10', 'source_node_key' => 'and-node-response', 'target_node_key' => 'task-communications-team', 'branch_type' => 'communications', 'join_node_key' => 'merge-response-teams'],
                ['id' => 'e11', 'source_node_key' => 'and-node-response', 'target_node_key' => 'task-operations-documentation', 'branch_type' => 'documentation', 'join_node_key' => 'merge-response-teams'],
                ['id' => 'e12', 'source_node_key' => 'and-node-response', 'target_node_key' => 'task-legal-review', 'branch_type' => 'legal', 'join_node_key' => 'merge-response-teams'],
                ['id' => 'e13', 'source_node_key' => 'and-node-response', 'target_node_key' => 'send-public-statement', 'branch_type' => 'public_statement', 'join_node_key' => 'merge-response-teams'],

                ['id' => 'e14', 'source_node_key' => 'task-containment-team', 'target_node_key' => 'merge-response-teams', 'branch_type' => 'default'],
                ['id' => 'e15', 'source_node_key' => 'task-communications-team', 'target_node_key' => 'merge-response-teams', 'branch_type' => 'default'],
                ['id' => 'e16', 'source_node_key' => 'task-operations-documentation', 'target_node_key' => 'merge-response-teams', 'branch_type' => 'default'],
                ['id' => 'e17', 'source_node_key' => 'task-legal-review', 'target_node_key' => 'merge-response-teams', 'branch_type' => 'default'],
                ['id' => 'e18', 'source_node_key' => 'send-public-statement', 'target_node_key' => 'merge-response-teams', 'branch_type' => 'default'],

                ['id' => 'e19', 'source_node_key' => 'merge-response-teams', 'target_node_key' => 'task-crisis-review-meeting', 'branch_type' => 'default'],
                ['id' => 'e20', 'source_node_key' => 'task-crisis-review-meeting', 'target_node_key' => 'if-crisis-contained', 'branch_type' => 'default'],

                ['id' => 'e21', 'source_node_key' => 'if-crisis-contained', 'target_node_key' => 'send-continue-response-notice', 'branch_type' => 'false'],
                ['id' => 'e22', 'source_node_key' => 'if-crisis-contained', 'target_node_key' => 'and-node-closure', 'branch_type' => 'true'],

                ['id' => 'e23', 'source_node_key' => 'and-node-closure', 'target_node_key' => 'task-post-incident-report', 'branch_type' => 'report', 'join_node_key' => 'merge-closure-tasks'],
                ['id' => 'e24', 'source_node_key' => 'and-node-closure', 'target_node_key' => 'task-update-crisis-policies', 'branch_type' => 'policies', 'join_node_key' => 'merge-closure-tasks'],

                ['id' => 'e25', 'source_node_key' => 'task-post-incident-report', 'target_node_key' => 'merge-closure-tasks', 'branch_type' => 'default'],
                ['id' => 'e26', 'source_node_key' => 'task-update-crisis-policies', 'target_node_key' => 'merge-closure-tasks', 'branch_type' => 'default'],

                ['id' => 'e27', 'source_node_key' => 'merge-closure-tasks', 'target_node_key' => 'action-generate-crisis-report', 'branch_type' => 'default'],
                ['id' => 'e28', 'source_node_key' => 'action-generate-crisis-report', 'target_node_key' => 'send-report-executives', 'branch_type' => 'default'],
                ['id' => 'e29', 'source_node_key' => 'send-report-executives', 'target_node_key' => 'send-notify-stakeholders', 'branch_type' => 'default'],
                ['id' => 'e30', 'source_node_key' => 'send-notify-stakeholders', 'target_node_key' => 'merge-containment-outcome', 'branch_type' => 'default'],
                ['id' => 'e31', 'source_node_key' => 'send-continue-response-notice', 'target_node_key' => 'merge-containment-outcome', 'branch_type' => 'default'],

                ['id' => 'e32', 'source_node_key' => 'merge-containment-outcome', 'target_node_key' => 'end', 'branch_type' => 'default'],
            ],
        ];
    }
}
