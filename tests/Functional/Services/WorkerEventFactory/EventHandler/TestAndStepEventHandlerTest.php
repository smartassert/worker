<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Services\WorkerEventFactory\EventHandler\TestAndStepEventHandler;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromStepEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromTestEventDataProviderTrait;

class TestAndStepEventHandlerTest extends AbstractEventHandlerTest
{
    use CreateFromStepEventDataProviderTrait;
    use CreateFromTestEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return array_merge(
            $this->createFromStepEventDataProvider(),
            $this->createFromTestEventEventDataProvider(),
        );
    }

    protected function getHandler(): ?EventHandlerInterface
    {
        $handler = self::getContainer()->get(TestAndStepEventHandler::class);

        return $handler instanceof TestAndStepEventHandler ? $handler : null;
    }
}
