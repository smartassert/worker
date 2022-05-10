<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\CompilationStartedEventHandler;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationStartedEventDataProviderTrait;

class CompilationStartedEventHandlerTest extends AbstractEventHandlerTest
{
    use CreateFromCompilationStartedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationStartedEventDataProvider();
    }

    protected function getHandler(): ?EventHandlerInterface
    {
        $handler = self::getContainer()->get(CompilationStartedEventHandler::class);

        return $handler instanceof CompilationStartedEventHandler ? $handler : null;
    }
}
