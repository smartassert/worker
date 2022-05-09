<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\EventCallbackFactoryInterface;
use App\Services\WorkerEventFactory\NoPayloadEventCallbackFactory;
use App\Tests\DataProvider\CallbackFactory\CreateFromExecutionCompletedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromExecutionStartedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobCompiledEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobCompletedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobFailedEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobReadyEventDataProviderTrait;

class NoPayloadEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromJobReadyEventDataProviderTrait;
    use CreateFromJobCompiledEventDataProviderTrait;
    use CreateFromExecutionStartedEventDataProviderTrait;
    use CreateFromExecutionCompletedEventDataProviderTrait;
    use CreateFromJobCompletedEventDataProviderTrait;
    use CreateFromJobFailedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return array_merge(
            $this->createFromJobCompiledEventDataProvider(),
            $this->createFromExecutionStartedEventDataProvider(),
            $this->createFromJobReadyEventDataProvider(),
            $this->createFromJobCompletedEventDataProvider(),
            $this->createFromExecutionCompletedEventDataProvider(),
            $this->createFromJobFailedEventDataProvider(),
        );
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(NoPayloadEventCallbackFactory::class);

        return $callbackFactory instanceof NoPayloadEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
