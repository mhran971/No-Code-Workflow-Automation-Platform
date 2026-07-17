<?php

namespace Modules\Workflows\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Modules\Workflows\Console\Commands\AdmitPendingInstancesCommand;
use Modules\Workflows\Console\Commands\ExpireOverdueInstancesCommand;
use Modules\Workflows\Console\Commands\ScanWorkflowTimersCommand;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Services\Execution\Admission\InstanceAdmissionService;
use Modules\Workflows\Services\Execution\Contracts\AiContentGenerator;
use Modules\Workflows\Services\Execution\ExecutionPlanCompiler;
use Modules\Workflows\Services\Execution\Executors\AiGeneratorExecutor;
use Modules\Workflows\Services\Execution\Executors\DynamicFlowExecutor;
use Modules\Workflows\Services\Execution\Executors\ForkNodeExecutor;
use Modules\Workflows\Services\Execution\Executors\FormTriggerExecutor;
use Modules\Workflows\Services\Execution\Executors\IfNodeExecutor;
use Modules\Workflows\Services\Execution\Executors\ManualTriggerExecutor;
use Modules\Workflows\Services\Execution\Executors\MergeNodeExecutor;
use Modules\Workflows\Services\Execution\Executors\SendEmailExecutor;
use Modules\Workflows\Services\Execution\Executors\SubWorkflowExecutor;
use Modules\Workflows\Services\Execution\Executors\SwitchNodeExecutor;
use Modules\Workflows\Services\Execution\Executors\TaskNodeExecutor;
use Modules\Workflows\Services\Execution\Executors\TerminationNodeExecutor;
use Modules\Workflows\Services\Execution\Executors\WebhookTriggerExecutor;
use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;
use Modules\Workflows\Services\Execution\FailureClassifier;
use Modules\Workflows\Services\Execution\NodeExecutorRegistry;
use Modules\Workflows\Services\Execution\NullAiContentGenerator;
use Modules\Workflows\Services\Execution\RetryPolicy;
use Modules\Workflows\Services\Execution\WorkflowDispatcher;
use Modules\Workflows\Services\Execution\WorkflowRuntime;
use Modules\Workflows\Services\Verification\ExpressionLanguageValidator;
use Modules\Workflows\Services\Verification\Rules\ContextualVerificationRule;
use Modules\Workflows\Services\Verification\Rules\ExpressionVerificationRule;
use Modules\Workflows\Services\Verification\Rules\GraphControlFlowVerificationRule;
use Modules\Workflows\Services\Verification\Rules\SyntaxVerificationRule;
use Modules\Workflows\Services\Verification\WorkflowDefinitionNormalizer;
use Modules\Workflows\Services\WorkflowDefinitionValidator;
use Modules\Workflows\Services\WorkflowManagementService;
use Modules\Workflows\Services\WorkflowVerificationService;

class WorkflowsServiceProvider extends ServiceProvider
{
    protected string $name = 'Workflows';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->commands([
            ScanWorkflowTimersCommand::class,
            AdmitPendingInstancesCommand::class,
            ExpireOverdueInstancesCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('workflows:scan-timers')->everyMinute()->withoutOverlapping();
            $schedule->command('workflows:admit-pending')->everyMinute()->withoutOverlapping();
            $schedule->command('workflows:expire-overdue')->daily()->withoutOverlapping();
        });

        $this->bootBroadcasting();
    }

    private function bootBroadcasting(): void
    {
        Broadcast::channel('workflow-instance.{instanceId}', function ($user, string $instanceId): bool {
            $instance = WorkflowInstance::find((int) $instanceId);

            return $instance !== null
                && (int) $instance->tenant_id === (int) $user->tenant_id;
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(WorkflowDefinitionNormalizer::class);
        $this->app->singleton(ExpressionLanguageValidator::class);
        $this->app->singleton(SyntaxVerificationRule::class);
        $this->app->singleton(GraphControlFlowVerificationRule::class);
        $this->app->singleton(ExpressionVerificationRule::class);
        $this->app->singleton(ContextualVerificationRule::class);
        $this->app->singleton(WorkflowVerificationService::class);
        $this->app->singleton(WorkflowDefinitionValidator::class);
        $this->app->singleton(WorkflowManagementService::class);

        // Execution engine — M0: foundations.
        $this->app->singleton(ExpressionEvaluator::class);
        $this->app->singleton(TemplateInterpolator::class);
        $this->app->singleton(ExecutionPlanCompiler::class);
        $this->app->singleton(NodeExecutorRegistry::class);

        // Execution engine — M1: reliability layer + runtime.
        $this->app->singleton(FailureClassifier::class);
        $this->app->singleton(RetryPolicy::class);
        $this->app->singleton(WorkflowRuntime::class);
        $this->app->singleton(WorkflowDispatcher::class);

        // Execution engine — M2: admission control.
        $this->app->singleton(InstanceAdmissionService::class);

        // AI generator contract — swap NullAiContentGenerator for a real provider when available.
        $this->app->bind(AiContentGenerator::class, NullAiContentGenerator::class);

        // Register node executors keyed by node type.
        $this->app->afterResolving(NodeExecutorRegistry::class, function (NodeExecutorRegistry $registry): void {
            // M1 executors
            $registry->register($this->app->make(ManualTriggerExecutor::class));
            $registry->register($this->app->make(FormTriggerExecutor::class));
            $registry->register($this->app->make(WebhookTriggerExecutor::class));
            $registry->register($this->app->make(IfNodeExecutor::class));
            $registry->register($this->app->make(SwitchNodeExecutor::class));
            $registry->register($this->app->make(TerminationNodeExecutor::class));
            $registry->register($this->app->make(SendEmailExecutor::class));
            $registry->register($this->app->make(AiGeneratorExecutor::class));

            // M2 executors
            $registry->register($this->app->make(ForkNodeExecutor::class));
            $registry->register($this->app->make(TaskNodeExecutor::class));
            $registry->register($this->app->make(SubWorkflowExecutor::class));
            $registry->register($this->app->make(DynamicFlowExecutor::class));

            // MergeNodeExecutor handles merge-and (its canonical type), merge-or, and the unified
            // 'merge' type (config.mergeMode selects parallel vs conditional at plan-compile time).
            $merge = $this->app->make(MergeNodeExecutor::class);
            $registry->register($merge);
            $registry->registerAs('merge-or', $merge);
            $registry->registerAs('merge', $merge);
        });
    }
}
