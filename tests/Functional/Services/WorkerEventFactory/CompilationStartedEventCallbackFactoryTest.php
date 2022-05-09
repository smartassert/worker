<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\CompilationStartedEventCallbackFactory;
use App\Services\WorkerEventFactory\EventCallbackFactoryInterface;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationStartedEventDataProviderTrait;

class CompilationStartedEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromCompilationStartedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationStartedEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(CompilationStartedEventCallbackFactory::class);

        return $callbackFactory instanceof CompilationStartedEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
