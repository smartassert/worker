<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Services\WorkerEventFactory\EventHandler\GenericEventHandler;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromStepEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromTestEventDataProviderTrait;

class GenericEventHandlerTest extends AbstractEventHandlerTest
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
        $handler = self::getContainer()->get(GenericEventHandler::class);

        return $handler instanceof GenericEventHandler ? $handler : null;
    }
}
