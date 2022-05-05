<?php

declare(strict_types=1);

namespace App\Tests\DataProvider\CallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
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
                'expectedReferenceSource' => '{{ job_label }}',
                'expectedCallback' => CallbackEntity::create(CallbackInterface::TYPE_JOB_STARTED, '', []),
            ],
        ];
    }
}
