<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\EventFactoryInterface;
use App\Services\WorkerEventFactory\EventHandler\NoPayloadEventFactory;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromExecutionCompletedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromExecutionStartedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobCompiledEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobCompletedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobFailedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobReadyEventDataProviderTrait;

class NoPayloadEventFactoryTest extends AbstractEventFactoryTest
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

    protected function getFactory(): ?EventFactoryInterface
    {
        $factory = self::getContainer()->get(NoPayloadEventFactory::class);

        return $factory instanceof NoPayloadEventFactory ? $factory : null;
    }
}
