<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\CompilationPassedEventCallbackFactory;
use App\Services\WorkerEventFactory\EventCallbackFactoryInterface;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationPassedEventDataProviderTrait;

class CompilationPassedEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromCompilationPassedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationPassedEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(CompilationPassedEventCallbackFactory::class);

        return $callbackFactory instanceof CompilationPassedEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
