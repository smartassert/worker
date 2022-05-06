<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
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
                'expectedCallback' => CallbackEntity::create(
                    CallbackEntity::TYPE_JOB_COMPILED,
                    '{{ job_label }}',
                    []
                ),
            ],
        ];
    }
}
