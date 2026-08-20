<?php

namespace App\Providers;

use App\Models\Task;
use App\Models\WorkEntry;
use App\Observers\TaskObserver;
use App\Observers\WorkEntryObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);
        WorkEntry::observe(WorkEntryObserver::class);
    }
}
