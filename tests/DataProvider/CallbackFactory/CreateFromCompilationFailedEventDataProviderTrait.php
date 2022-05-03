<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
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

        return [
            FailedEvent::class => [
                'event' => new FailedEvent(
                    '/app/source/test.yml',
                    $errorOutput
                ),
                'expectedCallback' => CallbackEntity::create(
                    CallbackInterface::TYPE_COMPILATION_FAILED,
                    '',
                    [
                        'source' => '/app/source/test.yml',
                        'output' => $errorOutputData,
                    ]
                ),
            ],
        ];
    }
}
