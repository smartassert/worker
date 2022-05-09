<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\EventCallbackFactoryInterface;
use App\Services\WorkerEventFactory\JobTimeoutEventCallbackFactory;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobTimeoutEventDataProviderTrait;

class JobTimeoutEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromJobTimeoutEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromJobTimeoutEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(JobTimeoutEventCallbackFactory::class);

        return $callbackFactory instanceof JobTimeoutEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
