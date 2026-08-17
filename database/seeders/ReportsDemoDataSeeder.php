<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;

class ReportsDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTenantOneData();
        $this->seedTenantTwoData();
    }

    protected function seedTenantOneData(): void
    {
        $tenantId = 1;
        $manager = User::find(2); // Candidate Alpha (Manager)
        $team = Team::find(1);    // Operations

        if (! $team || ! $manager) {
            return;
        }

        // Clean existing tasks, executions, instances for Tenant 1 to ensure a clean realistic distribution
        WorkflowTask::where('tenant_id', $tenantId)->delete();
        WorkflowNodeExecution::where('tenant_id', $tenantId)->delete();
        WorkflowInstance::where('tenant_id', $tenantId)->delete();

        // 1. Create or get active team members for Tenant 1
        $members = [
            [
                'first_name' => 'Sarah',
                'last_name' => 'Jenkins',
                'name' => 'Sarah Jenkins',
                'email' => 'sarah.jenkins@example.test',
                'position' => 'Senior Operations Specialist',
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Chang',
                'name' => 'Michael Chang',
                'email' => 'michael.chang@example.test',
                'position' => 'Workflow Coordinator',
            ],
            [
                'first_name' => 'Layla',
                'last_name' => 'Ahmed',
                'name' => 'Layla Ahmed',
                'email' => 'layla.ahmed@example.test',
                'position' => 'Automation Analyst',
            ],
        ];

        $teamUsers = [$manager];

        foreach ($members as $memData) {
            $user = User::firstOrCreate(
                ['email' => $memData['email']],
                [
                    'first_name' => $memData['first_name'],
                    'last_name' => $memData['last_name'],
                    'name' => $memData['name'],
                    'position' => $memData['position'],
                    'password' => Hash::make('password123'),
                    'tenant_id' => $tenantId,
                    'role' => Role::Employee,
                    'is_active' => true,
                ]
            );

            TeamMembership::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => 'active',
                ]
            );

            $teamUsers[] = $user;
        }

        // 2. Set workflows status to active and assign version numbers
        $workflows = Workflow::where('tenant_id', $tenantId)->where('team_id', $team->id)->get();
        foreach ($workflows as $wf) {
            $wf->update([
                'status' => WorkflowStatus::Active,
                'current_version_number' => 1,
            ]);
        }

        if ($workflows->isEmpty()) {
            return;
        }

        // 3. Generate Workflow Instances & Node Executions for Tenant 1 across 30 days
        $now = Carbon::now();
        $startDate = $now->copy()->subDays(28);

        $nodeTemplates = [
            ['key' => 'trigger_start', 'type' => 'manual-trigger', 'min_duration' => 0.1, 'max_duration' => 0.4],
            ['key' => 'fetch_crm_data', 'type' => 'http-request', 'min_duration' => 0.8, 'max_duration' => 2.5],
            ['key' => 'ai_document_analysis', 'type' => 'ai-prompt', 'min_duration' => 8.0, 'max_duration' => 18.5], // Bottleneck
            ['key' => 'human_review_task', 'type' => 'task-node', 'min_duration' => 1.0, 'max_duration' => 3.0],
            ['key' => 'send_slack_notification', 'type' => 'slack-notify', 'min_duration' => 0.4, 'max_duration' => 1.2],
            ['key' => 'save_to_database', 'type' => 'db-write', 'min_duration' => 0.2, 'max_duration' => 0.9],
        ];

        for ($day = 0; $day <= 28; $day++) {
            $currentDay = $startDate->copy()->addDays($day);
            $dailyCount = rand(2, 4);

            for ($i = 0; $i < $dailyCount; $i++) {
                $wf = $workflows->random();
                $runHour = rand(8, 18);
                $runMinute = rand(0, 59);
                $startedAt = $currentDay->copy()->setHour($runHour)->setMinute($runMinute)->setSecond(rand(0, 59));

                // Status distribution: 75% completed, 15% failed, 10% running/waiting
                $randStatus = rand(1, 100);
                if ($randStatus <= 75) {
                    $status = WorkflowInstanceStatus::Completed;
                    $durationSec = rand(25, 180);
                    $finishedAt = $startedAt->copy()->addSeconds($durationSec);
                    $error = null;
                } elseif ($randStatus <= 90) {
                    $status = WorkflowInstanceStatus::Failed;
                    $durationSec = rand(10, 60);
                    $finishedAt = $startedAt->copy()->addSeconds($durationSec);
                    $error = ['message' => 'API timeout while connecting to downstream service', 'node_key' => 'fetch_crm_data'];
                } else {
                    $status = $day > 25 ? WorkflowInstanceStatus::Running : WorkflowInstanceStatus::Waiting;
                    $finishedAt = null;
                    $error = null;
                }

                $instance = new WorkflowInstance();
                $instance->timestamps = false;
                $instance->fill([
                    'workflow_id' => $wf->id,
                    'workflow_version_id' => null,
                    'tenant_id' => $tenantId,
                    'status' => $status,
                    'trigger_type' => TriggerType::Manual,
                    'correlation_id' => (string) Str::uuid(),
                    'payload' => ['sample' => 'data', 'day' => $day],
                    'context' => ['processed' => true],
                    'error' => $error,
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                ]);
                $instance->created_at = $startedAt;
                $instance->updated_at = $finishedAt ?? $startedAt;
                $instance->save();

                // Create node executions for this instance
                $nodeStartTime = $startedAt->copy();
                foreach ($nodeTemplates as $idx => $nTemplate) {
                    if ($status === WorkflowInstanceStatus::Failed && $nTemplate['key'] === 'ai_document_analysis') {
                        // Fail at this step
                        $nodeDuration = rand(2, 5);
                        $nodeFinishTime = $nodeStartTime->copy()->addSeconds($nodeDuration);
                        $execution = new WorkflowNodeExecution();
                        $execution->timestamps = false;
                        $execution->fill([
                            'instance_id' => $instance->id,
                            'tenant_id' => $tenantId,
                            'node_key' => $nTemplate['key'],
                            'node_type' => $nTemplate['type'],
                            'status' => NodeExecutionStatus::Failed,
                            'attempt' => 1,
                            'idempotency_key' => "inst_{$instance->id}_node_{$nTemplate['key']}_1",
                            'started_at' => $nodeStartTime,
                            'finished_at' => $nodeFinishTime,
                            'error' => ['message' => 'Node execution failed'],
                        ]);
                        $execution->created_at = $nodeStartTime;
                        $execution->updated_at = $nodeFinishTime;
                        $execution->save();
                        break;
                    }

                    $nodeSec = rand((int) ($nTemplate['min_duration'] * 10), (int) ($nTemplate['max_duration'] * 10)) / 10.0;
                    $nodeFinishTime = $nodeStartTime->copy()->addMilliseconds((int) ($nodeSec * 1000));
                    $isCompleted = ($status === WorkflowInstanceStatus::Completed || $idx < 3);

                    $execution = new WorkflowNodeExecution();
                    $execution->timestamps = false;
                    $execution->fill([
                        'instance_id' => $instance->id,
                        'tenant_id' => $tenantId,
                        'node_key' => $nTemplate['key'],
                        'node_type' => $nTemplate['type'],
                        'status' => $isCompleted ? NodeExecutionStatus::Succeeded : NodeExecutionStatus::Running,
                        'attempt' => 1,
                        'idempotency_key' => "inst_{$instance->id}_node_{$nTemplate['key']}_1",
                        'started_at' => $nodeStartTime,
                        'finished_at' => $isCompleted ? $nodeFinishTime : null,
                    ]);
                    $execution->created_at = $nodeStartTime;
                    $execution->updated_at = $isCompleted ? $nodeFinishTime : $nodeStartTime;
                    $execution->save();

                    // If human review task, create a WorkflowTask
                    if ($nTemplate['key'] === 'human_review_task' && $isCompleted) {
                        /** @var User $assignee */
                        $assignee = collect($teamUsers)->random();
                        $taskCreated = $nodeStartTime->copy();
                        $taskDue = $taskCreated->copy()->addDays(2);

                        $isTaskCompleted = rand(1, 100) <= 80;
                        if ($isTaskCompleted) {
                            $isLate = rand(1, 100) <= 15; // 15% late
                            $taskHours = $isLate ? rand(50, 72) : rand(1, 30);
                            $taskFinished = $taskCreated->copy()->addHours($taskHours);
                            $taskStatus = 'completed';
                        } else {
                            $isOverdue = $taskDue->isPast();
                            $taskFinished = null;
                            $taskStatus = 'open';
                        }

                        $task = new WorkflowTask();
                        $task->timestamps = false;
                        $task->fill([
                            'instance_id' => $instance->id,
                            'execution_id' => $execution->id,
                            'tenant_id' => $tenantId,
                            'node_key' => $nTemplate['key'],
                            'assignee_id' => $assignee->id,
                            'title' => "Review {$wf->name} Request #" . rand(1000, 9999),
                            'description' => "Please review and approve the request submitted on {$taskCreated->toDateString()}.",
                            'input_schema' => ['notes' => 'string', 'approved' => 'boolean'],
                            'status' => $taskStatus,
                            'due_at' => $taskDue,
                            'response' => $isTaskCompleted ? ['approved' => true, 'comment' => 'Verified and approved'] : null,
                            'completed_by_id' => $isTaskCompleted ? $assignee->id : null,
                            'completed_at' => $taskFinished,
                        ]);
                        $task->created_at = $taskCreated;
                        $task->updated_at = $taskFinished ?? $taskCreated;
                        $task->save();
                    }

                    $nodeStartTime = $nodeFinishTime->copy()->addSeconds(1);
                }
            }
        }
    }

    protected function seedTenantTwoData(): void
    {
        $tenantId = 2;
        $manager = User::find(5); // string1 string1 (Manager)
        $team = Team::find(2);    // string

        if (! $team || ! $manager) {
            return;
        }

        // Clean existing tasks, executions, instances for Tenant 2
        WorkflowTask::where('tenant_id', $tenantId)->delete();
        WorkflowNodeExecution::where('tenant_id', $tenantId)->delete();
        WorkflowInstance::where('tenant_id', $tenantId)->delete();

        $members = User::where('tenant_id', $tenantId)->whereIn('id', [4, 5, 6])->get();
        $workflows = Workflow::where('tenant_id', $tenantId)->where('team_id', $team->id)->get();

        foreach ($workflows as $wf) {
            $wf->update([
                'status' => WorkflowStatus::Active,
                'current_version_number' => 1,
            ]);
        }

        if ($workflows->isEmpty()) {
            return;
        }

        $now = Carbon::now();
        $startDate = $now->copy()->subDays(20);

        for ($day = 0; $day <= 20; $day++) {
            $currentDay = $startDate->copy()->addDays($day);
            $dailyCount = rand(1, 3);

            for ($i = 0; $i < $dailyCount; $i++) {
                $wf = $workflows->random();
                $startedAt = $currentDay->copy()->setHour(rand(9, 17))->setMinute(rand(0, 59));
                $isSuccess = rand(1, 100) <= 80;
                $durationSec = rand(15, 90);
                $finishedAt = $startedAt->copy()->addSeconds($durationSec);

                $status = $isSuccess ? WorkflowInstanceStatus::Completed : WorkflowInstanceStatus::Failed;

                $instance = new WorkflowInstance();
                $instance->timestamps = false;
                $instance->fill([
                    'workflow_id' => $wf->id,
                    'tenant_id' => $tenantId,
                    'status' => $status,
                    'trigger_type' => TriggerType::Manual,
                    'correlation_id' => (string) Str::uuid(),
                    'payload' => ['tenant2' => 'sample'],
                    'context' => ['status' => 'ok'],
                    'error' => $isSuccess ? null : ['message' => 'Execution error in step 2'],
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                ]);
                $instance->created_at = $startedAt;
                $instance->updated_at = $finishedAt;
                $instance->save();

                // Execution
                $execution = new WorkflowNodeExecution();
                $execution->timestamps = false;
                $execution->fill([
                    'instance_id' => $instance->id,
                    'tenant_id' => $tenantId,
                    'node_key' => 'process_step',
                    'node_type' => 'http-request',
                    'status' => $isSuccess ? NodeExecutionStatus::Succeeded : NodeExecutionStatus::Failed,
                    'attempt' => 1,
                    'idempotency_key' => "inst_{$instance->id}_step_1",
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                ]);
                $execution->created_at = $startedAt;
                $execution->updated_at = $finishedAt;
                $execution->save();

                // Create a task
                if ($members->isNotEmpty()) {
                    $assignee = $members->random();
                    $taskDue = $startedAt->copy()->addDays(2);
                    $taskDone = $isSuccess;
                    $taskCompletedAt = $taskDone ? $startedAt->copy()->addHours(rand(2, 24)) : null;

                    $task = new WorkflowTask();
                    $task->timestamps = false;
                    $task->fill([
                        'instance_id' => $instance->id,
                        'execution_id' => $execution->id,
                        'tenant_id' => $tenantId,
                        'node_key' => 'process_step',
                        'assignee_id' => $assignee->id,
                        'title' => "Tenant 2 Task - {$wf->name}",
                        'description' => "Complete validation for {$wf->name}",
                        'status' => $taskDone ? 'completed' : ($taskDue->isPast() ? 'open' : 'open'),
                        'due_at' => $taskDue,
                        'response' => $taskDone ? ['done' => true] : null,
                        'completed_by_id' => $taskDone ? $assignee->id : null,
                        'completed_at' => $taskCompletedAt,
                    ]);
                    $task->created_at = $startedAt;
                    $task->updated_at = $taskCompletedAt ?? $startedAt;
                    $task->save();
                }
            }
        }
    }
}
