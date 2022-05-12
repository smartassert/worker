<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\SourceCompilationFailedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Tests\Mock\MockSuiteManifest;

trait CreateFromCompilationPassedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromCompilationPassedEventDataProvider(): array
    {
        $source = '/app/source/test.yml';

        return [
            SourceCompilationFailedEvent::class => [
                'event' => new SourceCompilationPassedEvent($source, (new MockSuiteManifest())->getMock()),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::COMPILATION_PASSED,
                    '{{ job_label }}' . $source,
                    [
                        'source' => $source,
                    ]
                ),
            ],
        ];
    }
}
