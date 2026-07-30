<?php

namespace Modules\Notifications\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Notifications\Jobs\TaskDueSoonNotificationJob;
use Modules\Notifications\Jobs\TaskOverdueNotificationJob;

class NotificationsServiceProvider extends ServiceProvider
{
    protected string $name = 'Notifications';

    public function boot(): void
    {
        $this->loadTranslationsFrom(module_path($this->name, 'lang'), 'notifications');

        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);

            $schedule->job(new TaskDueSoonNotificationJob)->hourly()->withoutOverlapping();
            $schedule->job(new TaskOverdueNotificationJob)->hourly()->withoutOverlapping();
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
