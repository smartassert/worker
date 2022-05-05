<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EventCallbackFactory;

use App\Services\EventCallbackFactory\EventCallbackFactoryInterface;
use App\Services\EventCallbackFactory\TestAndStepEventCallbackFactory;
use App\Tests\DataProvider\CallbackFactory\CreateFromStepEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromTestEventDataProviderTrait;

class TestAndStepEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromStepEventDataProviderTrait;
    use CreateFromTestEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return array_merge(
            $this->createFromStepEventDataProvider(),
            $this->createFromTestEventEventDataProvider(),
        );
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(TestAndStepEventCallbackFactory::class);

        return $callbackFactory instanceof TestAndStepEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
