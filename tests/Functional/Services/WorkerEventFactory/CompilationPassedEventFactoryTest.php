<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory;

use App\Services\WorkerEventFactory\CompilationPassedEventFactory;
use App\Services\WorkerEventFactory\EventFactoryInterface;
use App\Tests\DataProvider\CallbackFactory\CreateFromCompilationPassedEventDataProviderTrait;

class CompilationPassedEventFactoryTest extends AbstractEventFactoryTest
{
    use CreateFromCompilationPassedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return $this->createFromCompilationPassedEventDataProvider();
    }

    protected function getCallbackFactory(): ?EventFactoryInterface
    {
        $callbackFactory = self::getContainer()->get(CompilationPassedEventFactory::class);

        return $callbackFactory instanceof CompilationPassedEventFactory
            ? $callbackFactory
            : null;
    }
}
