<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\CompilationFailedEventHandler;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationFailedEventDataProviderTrait;

class CompilationFailedEventHandlerTest extends AbstractEventHandlerTest
{
    use CreateFromCompilationFailedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationFailedEventDataProvider();
    }

    protected function getHandler(): ?EventHandlerInterface
    {
        $handler = self::getContainer()->get(CompilationFailedEventHandler::class);

        return $handler instanceof CompilationFailedEventHandler ? $handler : null;
    }
}
