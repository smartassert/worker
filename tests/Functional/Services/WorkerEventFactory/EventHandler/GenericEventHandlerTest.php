<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Services\WorkerEventFactory\EventHandler\GenericEventHandler;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationFailedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationPassedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromCompilationStartedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromExecutionCompletedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromExecutionStartedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobCompiledEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobCompletedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobFailedEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobReadyEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromJobTimeoutEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromStepEventDataProviderTrait;
use App\Tests\DataProvider\WorkerEventFactory\CreateFromTestEventDataProviderTrait;

class GenericEventHandlerTest extends AbstractEventHandlerTest
{
    use CreateFromStepEventDataProviderTrait;
    use CreateFromTestEventDataProviderTrait;
    use CreateFromJobTimeoutEventDataProviderTrait;
    use CreateFromJobReadyEventDataProviderTrait;
    use CreateFromJobCompiledEventDataProviderTrait;
    use CreateFromExecutionStartedEventDataProviderTrait;
    use CreateFromExecutionCompletedEventDataProviderTrait;
    use CreateFromJobCompletedEventDataProviderTrait;
    use CreateFromJobFailedEventDataProviderTrait;
    use CreateFromCompilationFailedEventDataProviderTrait;
    use CreateFromCompilationPassedEventDataProviderTrait;
    use CreateFromCompilationStartedEventDataProviderTrait;

    public function createDataProvider(): array
    {
        return array_merge(
            $this->createFromStepEventDataProvider(),
            $this->createFromTestEventEventDataProvider(),
            $this->createFromJobTimeoutEventDataProvider(),
            $this->createFromJobCompiledEventDataProvider(),
            $this->createFromExecutionStartedEventDataProvider(),
            $this->createFromJobReadyEventDataProvider(),
            $this->createFromJobCompletedEventDataProvider(),
            $this->createFromExecutionCompletedEventDataProvider(),
            $this->createFromJobFailedEventDataProvider(),
            $this->createFromCompilationFailedEventDataProvider(),
            $this->createFromCompilationPassedEventDataProvider(),
            $this->createFromCompilationStartedEventDataProvider(),
        );
    }

    protected function getHandler(): ?GenericEventHandler
    {
        $handler = self::getContainer()->get(GenericEventHandler::class);

        return $handler instanceof GenericEventHandler ? $handler : null;
    }
}
