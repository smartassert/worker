<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Event\JobFailedEvent;

trait CreateFromJobFailedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobFailedEventDataProvider(): array
    {
        return [
            JobFailedEvent::class => [
                'event' => new JobFailedEvent(),
                'expectedCallback' => CallbackEntity::create(
                    CallbackEntity::TYPE_JOB_FAILED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
