<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\EventFactoryInterface;
use App\Services\WorkerEventFactory\JobTimeoutEventFactory;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobTimeoutEventDataProviderTrait;

class JobTimeoutEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromJobTimeoutEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromJobTimeoutEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(JobTimeoutEventFactory::class);

        return $callbackFactory instanceof JobTimeoutEventFactory
            ? $callbackFactory
            : null;
    }
}
