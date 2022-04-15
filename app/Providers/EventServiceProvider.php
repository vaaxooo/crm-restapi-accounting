<?php

namespace App\Providers;

use App\Jobs\BalanceHistoryJob;
use App\Jobs\ExpensesJob;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Jobs\IncomesJob;
use App\Jobs\PayoutsJob;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(IncomesJob::class . '@handle', fn ($job) => $job->handle());
        $this->app->bind(ExpensesJob::class . '@handle', fn ($job) => $job->handle());
        $this->app->bind(PayoutsJob::class . '@handle', fn ($job) => $job->handle());
        $this->app->bind(BalanceHistoryJob::class . '@handle', fn ($job) => $job->handle());
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
