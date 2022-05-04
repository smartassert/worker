<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Event\SourceCompilation\StartedEvent;

trait CreateFromCompilationStartedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromCompilationStartedEventDataProvider(): array
    {
        $source = '/app/source/test.yml';

        return [
            StartedEvent::class => [
                'event' => new StartedEvent($source),
                'expectedReferenceSource' => '{{ job_label }}' . $source,
                'expectedCallback' => CallbackEntity::create(
                    CallbackInterface::TYPE_COMPILATION_STARTED,
                    '{{ job_label }}' . $source,
                    [
                        'source' => $source,
                    ]
                ),
            ],
        ];
    }
}
