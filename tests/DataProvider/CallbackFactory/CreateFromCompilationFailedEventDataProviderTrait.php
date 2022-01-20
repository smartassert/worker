<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Event\SourceCompilation\FailedEvent;
use App\Tests\Mock\Entity\MockCallback;
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
                'expectedCallback' => (new MockCallback())
                    ->withGetTypeCall(CallbackInterface::TYPE_COMPILATION_FAILED)
                    ->withGetPayloadCall([
                        'source' => '/app/source/test.yml',
                        'output' => $errorOutputData,
                    ])
                    ->getMock(),
            ],
        ];
    }
}
