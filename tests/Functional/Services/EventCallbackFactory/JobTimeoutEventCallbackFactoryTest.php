<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EventCallbackFactory;

use App\Services\EventCallbackFactory\EventCallbackFactoryInterface;
use App\Services\EventCallbackFactory\JobTimeoutEventCallbackFactory;
use App\Tests\DataProvider\CallbackFactory\CreateFromJobTimeoutEventDataProviderTrait;

class JobTimeoutEventCallbackFactoryTest extends AbstractEventCallbackFactoryTest
{
    use CreateFromJobTimeoutEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromJobTimeoutEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventCallbackFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(JobTimeoutEventCallbackFactory::class);

        return $callbackFactory instanceof JobTimeoutEventCallbackFactory
            ? $callbackFactory
            : null;
    }
}
