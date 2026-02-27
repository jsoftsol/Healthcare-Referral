<?php
namespace App\Providers;

use App\Events\ReferralSubmitted;
use App\Events\ReferralTriaged;
use App\Listeners\DispatchAiTriageListener;
use App\Listeners\LogReferralAuditListener;
use App\Listeners\NotifyStaffOnTriageListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ReferralSubmitted::class => [
            DispatchAiTriageListener::class,
        ],
        ReferralTriaged::class   => [
            NotifyStaffOnTriageListener::class,
        ],
    ];

    protected $subscribe = [
        LogReferralAuditListener::class,
    ];

    public function boot(): void
    {}
}
