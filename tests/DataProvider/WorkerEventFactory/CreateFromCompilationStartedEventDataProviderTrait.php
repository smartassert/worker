<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\SourceCompilation\SourceCompilationStartedEvent;

trait CreateFromCompilationStartedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromCompilationStartedEventDataProvider(): array
    {
        $source = '/app/source/test.yml';

        return [
            SourceCompilationStartedEvent::class => [
                'event' => new SourceCompilationStartedEvent($source),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::COMPILATION_STARTED,
                    '{{ job_label }}' . $source,
                    [
                        'source' => $source,
                    ]
                ),
            ],
        ];
    }
}
