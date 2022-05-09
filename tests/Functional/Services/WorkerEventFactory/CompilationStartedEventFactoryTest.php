<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\CompilationStartedEventFactory;
use App\Services\WorkerEventFactory\EventFactoryInterface;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationStartedEventDataProviderTrait;

class CompilationStartedEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromCompilationStartedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationStartedEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(CompilationStartedEventFactory::class);

        return $callbackFactory instanceof CompilationStartedEventFactory
            ? $callbackFactory
            : null;
    }
}
