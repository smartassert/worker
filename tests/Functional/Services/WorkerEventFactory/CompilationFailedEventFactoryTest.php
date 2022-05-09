<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\CompilationFailedEventFactory;
use App\Services\WorkerEventFactory\EventFactoryInterface;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationFailedEventDataProviderTrait;

class CompilationFailedEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromCompilationFailedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationFailedEventDataProvider();
    }

    protected function getFactory(): ?EventFactoryInterface
    {
        $factory = self::getContainer()->get(CompilationFailedEventFactory::class);

        return $factory instanceof CompilationFailedEventFactory ? $factory : null;
    }
}
