<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Event\JobTimeoutEvent;

trait CreateFromJobTimeoutEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobTimeoutEventDataProvider(): array
    {
        return [
            JobTimeoutEvent::class => [
                'event' => new JobTimeoutEvent(150),
                'expectedCallback' => CallbackEntity::create(
                    CallbackInterface::TYPE_JOB_TIME_OUT,
                    '',
                    [
                        'maximum_duration_in_seconds' => 150,
                    ]
                ),
            ],
        ];
    }
}
