<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\CompilationFailedEventFactory;
use App\Services\WorkerEventFactory\EventFactoryInterface;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationFailedEventDataProviderTrait;

class CompilationFailedEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromCompilationFailedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationFailedEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(CompilationFailedEventFactory::class);

        return $callbackFactory instanceof CompilationFailedEventFactory
            ? $callbackFactory
            : null;
    }
}
