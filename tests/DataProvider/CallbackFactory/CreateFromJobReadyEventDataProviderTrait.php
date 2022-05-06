<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Event\JobReadyEvent;

trait CreateFromJobReadyEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobReadyEventDataProvider(): array
    {
        return [
            JobReadyEvent::class => [
                'event' => new JobReadyEvent(),
                'expectedCallback' => CallbackEntity::create(
                    CallbackEntity::TYPE_JOB_STARTED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
