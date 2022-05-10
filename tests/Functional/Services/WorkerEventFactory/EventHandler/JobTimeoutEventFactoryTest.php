<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\EventFactoryInterface;
use App\Services\WorkerEventFactory\EventHandler\JobTimeoutEventFactory;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobTimeoutEventDataProviderTrait;

class JobTimeoutEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromJobTimeoutEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromJobTimeoutEventDataProvider();
    }

    protected function getFactory(): ?EventFactoryInterface
    {
        $factory = self::getContainer()->get(JobTimeoutEventFactory::class);

        return $factory instanceof JobTimeoutEventFactory ? $factory : null;
    }
}
