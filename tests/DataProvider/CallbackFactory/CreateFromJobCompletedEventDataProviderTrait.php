<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Event\JobCompletedEvent;

trait CreateFromJobCompletedEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobCompletedEventDataProvider(): array
    {
        return [
            JobCompletedEvent::class => [
                'event' => new JobCompletedEvent(),
                'expectedCallback' => CallbackEntity::create(
                    CallbackEntity::TYPE_JOB_COMPLETED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
