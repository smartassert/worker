<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\WorkerEvent;
use App\Event\JobCompiledEvent;

trait CreateFromJobCompiledEventDataProviderTrait
{
    /**
     * @return array<mixed>
     */
    public function createFromJobCompiledEventDataProvider(): array
    {
        return [
            JobCompiledEvent::class => [
                'event' => new JobCompiledEvent(),
                'expectedCallback' => WorkerEvent::create(
                    WorkerEvent::TYPE_JOB_COMPILED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
