<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\EventFactoryInterface;
use App\Services\WorkerEventFactory\TestAndStepEventFactory;
use App\Tests\DataProvider\CallbackFactory\CreateFromStepEventDataProviderTrait;
use App\Tests\DataProvider\CallbackFactory\CreateFromTestEventDataProviderTrait;

class TestAndStepEventFactoryTest extends AbstractEventFactoryTest
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

    protected function getCallbackFactory(): ?EventFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(TestAndStepEventFactory::class);

        return $callbackFactory instanceof TestAndStepEventFactory
            ? $callbackFactory
            : null;
    }
}
