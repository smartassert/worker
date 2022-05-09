<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\CompilationPassedEventFactory;
use App\Services\WorkerEventFactory\EventFactoryInterface;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationPassedEventDataProviderTrait;

class CompilationPassedEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromCompilationPassedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationPassedEventDataProvider();
    }

    protected function getFactory(): ?EventFactoryInterface
    {
        $factory = self::getContainer()->get(CompilationPassedEventFactory::class);

        return $factory instanceof CompilationPassedEventFactory ? $factory : null;
    }
}
