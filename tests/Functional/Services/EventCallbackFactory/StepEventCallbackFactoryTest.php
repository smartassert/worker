<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EventCallbackFactory;

use App\Services\EventCallbackFactory\EventCallbackFactoryInterface;
use App\Services\EventCallbackFactory\StepEventCallbackFactory;
use App\Tests\DataProvider\CallbackFactory\CreateFromStepEventDataProviderTrait;

class StepEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromStepEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromStepEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(StepEventCallbackFactory::class);

        return $callbackFactory instanceof StepEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
