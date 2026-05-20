<?php

namespace App\Providers;

use App\Events\Deoris\AdmissionApproved;
use App\Events\Deoris\AdmissionRejected;
use App\Events\Deoris\ApplicationStatusChanged;
use App\Events\Deoris\ApplicationSubmitted;
use App\Events\Deoris\ExamAssigned;
use App\Events\Deoris\ExamCompleted;
use App\Events\Deoris\ExamScoreReleased;
use App\Listeners\Deoris\BroadcastDeorisDomainEvent;
use App\Listeners\Deoris\Inbound\HandleMedicalApproved;
use App\Listeners\Deoris\Inbound\HandleStudentEnrolled;
use App\Listeners\Deoris\Inbound\HandleTuitionPaid;
use App\Listeners\Deoris\LogDeorisActivity;
use App\Services\Integration\InboundEventDispatcher;
use Deoris\Integration\EventPublisher;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DeorisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventPublisher::class, function () {
            $secret = config('deoris.portal.event_secret');

            // Pass only the base portal URL - EventPublisher handles the path internally
            $portalUrl = (string) config('deoris.portal.url', config('app.portal_url'));

            return new EventPublisher(
                portalUrl: $portalUrl,
                moduleSecret: is_string($secret) ? $secret : '',
                redisChannel: (string) config('deoris.portal.redis_channel', 'deoris.events'),
            );
        });

        $this->app->singleton(InboundEventDispatcher::class, function ($app) {
            $dispatcher = new InboundEventDispatcher;

            foreach ([
                HandleTuitionPaid::class,
                HandleMedicalApproved::class,
                HandleStudentEnrolled::class,
            ] as $handlerClass) {
                $dispatcher->register($app->make($handlerClass));
            }

            return $dispatcher;
        });
    }

    public function boot(): void
    {
        Broadcast::routes(['middleware' => ['web']]);

        $domainEvents = [
            ApplicationSubmitted::class,
            ApplicationStatusChanged::class,
            AdmissionApproved::class,
            AdmissionRejected::class,
            ExamAssigned::class,
            ExamCompleted::class,
            ExamScoreReleased::class,
        ];

        foreach ($domainEvents as $eventClass) {
            Event::listen($eventClass, LogDeorisActivity::class);
            Event::listen($eventClass, BroadcastDeorisDomainEvent::class);
        }
    }
}
