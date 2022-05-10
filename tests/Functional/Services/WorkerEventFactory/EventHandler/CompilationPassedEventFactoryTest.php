<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\CompilationPassedEventFactory;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationPassedEventDataProviderTrait;

class CompilationPassedEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromCompilationPassedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationPassedEventDataProvider();
    }

    protected function getFactory(): ?EventHandlerInterface
    {
        $factory = self::getContainer()->get(CompilationPassedEventFactory::class);

        return $factory instanceof CompilationPassedEventFactory ? $factory : null;
    }
}
