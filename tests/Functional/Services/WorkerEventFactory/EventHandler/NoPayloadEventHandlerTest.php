<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Services\WorkerEventFactory\EventHandler\NoPayloadEventHandler;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromExecutionCompletedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromExecutionStartedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobCompiledEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobCompletedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobFailedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobReadyEventDataProviderTrait;

class NoPayloadEventHandlerTest extends AbstractEventHandlerTest
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

    protected function getHandler(): ?EventHandlerInterface
    {
        $handler = self::getContainer()->get(NoPayloadEventHandler::class);

        return $handler instanceof NoPayloadEventHandler ? $handler : null;
    }
}
