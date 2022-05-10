<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\CompilationFailedEventFactory;
use App\Services\WorkerEventFactory\EventHandler\EventHandlerInterface;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationFailedEventDataProviderTrait;

class CompilationFailedEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromCompilationFailedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationFailedEventDataProvider();
    }

    protected function getFactory(): ?EventHandlerInterface
    {
        $factory = self::getContainer()->get(CompilationFailedEventFactory::class);

        return $factory instanceof CompilationFailedEventFactory ? $factory : null;
    }
}
