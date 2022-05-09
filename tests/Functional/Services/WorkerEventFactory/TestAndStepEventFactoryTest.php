<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\EventFactoryInterface;
use App\Services\WorkerEventFactory\TestAndStepEventFactory;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromStepEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromTestEventDataProviderTrait;

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

    protected function getFactory(): ?EventFactoryInterface
    {
        $factory = self::getContainer()->get(TestAndStepEventFactory::class);

        return $factory instanceof TestAndStepEventFactory ? $factory : null;
    }
}
