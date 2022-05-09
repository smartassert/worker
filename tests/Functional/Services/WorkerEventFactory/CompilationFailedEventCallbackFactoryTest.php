<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\CompilationFailedEventCallbackFactory;
use App\Services\WorkerEventFactory\EventCallbackFactoryInterface;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationFailedEventDataProviderTrait;

class CompilationFailedEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromCompilationFailedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationFailedEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(CompilationFailedEventCallbackFactory::class);

        return $callbackFactory instanceof CompilationFailedEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
