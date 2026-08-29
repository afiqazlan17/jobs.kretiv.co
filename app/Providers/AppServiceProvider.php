<?php

namespace App\Providers;

use App\Models\Job;
use App\Models\User;
use App\Observers\JobObserver;
use App\Observers\UserObserver;
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
        Job::observe(JobObserver::class);
        User::observe(UserObserver::class);
    }
}
