<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\WorkerEventFactory;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Event\SourceCompilation\FailedEvent;
use webignition\BasilCompilerModels\ErrorOutputInterface;

trait CreateFromCompilationFailedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromCompilationFailedEventDataProvider(): array
    {
        $errorOutputData = [
            'error-output-key' => 'error-output-value',
        ];

        $errorOutput = \Mockery::mock(ErrorOutputInterface::class);
        $errorOutput
            ->shouldReceive('getData')
            ->andReturn($errorOutputData)
        ;

        $source = '/app/source/test.yml';

        return [
            FailedEvent::class => [
                'event' => new FailedEvent($source, $errorOutput),
                'expectedWorkerEvent' => WorkerEvent::create(
                    WorkerEventType::COMPILATION_FAILED,
                    '{{ job_label }}' . $source,
                    [
                        'source' => '/app/source/test.yml',
                        'output' => $errorOutputData,
                    ]
                ),
            ],
        ];
    }
}
