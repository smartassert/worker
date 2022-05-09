<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\WorkerEvent;
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
                'expectedCallback' => WorkerEvent::create(
                    WorkerEvent::TYPE_JOB_STARTED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
