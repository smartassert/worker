<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\CompilationStartedEventFactory;
use App\Services\WorkerEventFactory\EventHandler\EventFactoryInterface;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationStartedEventDataProviderTrait;

class CompilationStartedEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromCompilationStartedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationStartedEventDataProvider();
    }

    protected function getFactory(): ?EventFactoryInterface
    {
        $factory = self::getContainer()->get(CompilationStartedEventFactory::class);

        return $factory instanceof CompilationStartedEventFactory ? $factory : null;
    }
}
