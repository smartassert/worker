<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EventCallbackFactory;

use App\Services\EventCallbackFactory\CompilationStartedEventCallbackFactory;
use App\Services\EventCallbackFactory\EventCallbackFactoryInterface;
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
