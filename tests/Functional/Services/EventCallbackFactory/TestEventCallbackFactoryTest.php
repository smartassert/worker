<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EventCallbackFactory;

use App\Services\EventCallbackFactory\EventCallbackFactoryInterface;
use App\Services\EventCallbackFactory\TestEventCallbackFactory;
use App\Tests\DataProvider\CallbackFactory\CreateFromTestEventDataProviderTrait;

class TestEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromTestEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromTestEventEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(TestEventCallbackFactory::class);

        return $callbackFactory instanceof TestEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
