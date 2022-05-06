<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
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
                'expectedCallback' => CallbackEntity::create(
                    CallbackEntity::TYPE_COMPILATION_FAILED,
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
