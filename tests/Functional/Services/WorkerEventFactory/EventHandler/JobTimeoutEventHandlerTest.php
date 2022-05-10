<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Services\WorkerEventFactory\EventHandler\JobTimeoutEventHandler;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobTimeoutEventDataProviderTrait;

class JobTimeoutEventHandlerTest extends AbstractEventHandlerTest
{
    use CreateFromJobTimeoutEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromJobTimeoutEventDataProvider();
    }

    protected function getHandler(): ?EventHandlerInterface
    {
        $handler = self::getContainer()->get(JobTimeoutEventHandler::class);

        return $handler instanceof JobTimeoutEventHandler ? $handler : null;
    }
}
