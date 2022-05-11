<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageDispatcher\DeliverEventDispatcher;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\SourceCompilation\FailedEvent;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use webignition\BasilCompilerModels\ErrorOutputInterface;

class SourceCompilationFailedDispatcherTest extends AbstractDeliverEventDispatcherTest
{
    private const RELATIVE_SOURCE = 'Test/test.yml';

    /**
     * @return array<mixed>
     */
    public function createWorkerEventAndDispatchDeliverEventMessageDataProvider(): array
    {
        $failureOutputData = [
            'compile-failure-key' => 'value',
        ];

        $failureOutput = \Mockery::mock(ErrorOutputInterface::class);
        $failureOutput
            ->shouldReceive('getData')
            ->andReturn($failureOutputData)
        ;

        return [
            FailedEvent::class => [
                'eventCreator' => function () use ($failureOutput): FailedEvent {
                    return new FailedEvent(self::RELATIVE_SOURCE, $failureOutput);
                },
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::COMPILATION_FAILED,
                    md5(self::JOB_LABEL . self::RELATIVE_SOURCE),
                    [
                        'source' => self::RELATIVE_SOURCE,
                        'output' => $failureOutputData,
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
