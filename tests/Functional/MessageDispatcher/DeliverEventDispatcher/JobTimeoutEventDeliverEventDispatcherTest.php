<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\JobTimeoutEvent;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;

class JobTimeoutEventDeliverEventDispatcherTest extends AbstractDeliverEventDispatcherTest
{
    /**
     * @return array<mixed>
     */
    public function createWorkerEventAndDispatchDeliverEventMessageDataProvider(): array
    {
        return [
            JobTimeoutEvent::class => [
                'eventCreator' => function (): JobTimeoutEvent {
                    return new JobTimeoutEvent(10);
                },
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::JOB_TIME_OUT,
                    md5(self::JOB_LABEL),
                    [
                        'maximum_duration_in_seconds' => 10,
                    ]
                ),
            ],
        ];
    }

    protected function getEntityClassNamesToRemove(): array
    {
        return [
            Job::class,
        ];
    }

    protected function getEnvironmentSetup(): EnvironmentSetup
    {
        return (new EnvironmentSetup())
            ->withJobSetup(
                (new JobSetup())
                    ->withLabel(self::JOB_LABEL)
            )
        ;
    }

    protected function getEventCreatorArguments(): array
    {
        return [];
    }
}
