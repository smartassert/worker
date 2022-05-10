<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\CompilationPassedEventHandler;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationPassedEventDataProviderTrait;

class CompilationPassedEventHandlerTest extends AbstractEventHandlerTest
{
    use CreateFromCompilationPassedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationPassedEventDataProvider();
    }

    protected function getHandler(): ?EventHandlerInterface
    {
        $handler = self::getContainer()->get(CompilationPassedEventHandler::class);

        return $handler instanceof CompilationPassedEventHandler ? $handler : null;
    }
}
