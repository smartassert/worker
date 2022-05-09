<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\WorkerEvent;
use App\Event\SourceCompilation\FailedEvent;
use App\Event\SourceCompilation\PassedEvent;
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
            FailedEvent::class => [
                'event' => new PassedEvent($source, (new MockSuiteManifest())->getMock()),
                'expectedCallback' => WorkerEvent::create(
                    WorkerEvent::TYPE_COMPILATION_PASSED,
                    '{{ job_label }}' . $source,
                    [
                        'source' => $source,
                    ]
                ),
            ],
        ];
    }
}
