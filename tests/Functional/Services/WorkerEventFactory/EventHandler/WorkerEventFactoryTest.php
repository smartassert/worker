<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\WorkerEventFactory\EventHandler;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Event\EventInterface;
use App\Services\WorkerEventFactory;
use App\Tests\AbstractBaseFunctionalTest;
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
use webignition\ObjectReflector\ObjectReflector;

class WorkerEventFactoryTest extends AbstractBaseFunctionalTest
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

    private WorkerEventFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(WorkerEventFactory::class);
        \assert($factory instanceof WorkerEventFactory);
        $this->factory = $factory;
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreateForEvent(EventInterface $event, WorkerEvent $expectedWorkerEvent): void
    {
        $jobLabel = md5((string) rand());
        $job = Job::create($jobLabel, '', 600);

        $workerEvent = $this->factory->createForEvent($job, $event);

        $expectedReferenceSource = str_replace('{{ job_label }}', $jobLabel, $expectedWorkerEvent->getReference());
        ObjectReflector::setProperty(
            $expectedWorkerEvent,
            WorkerEvent::class,
            'reference',
            md5($expectedReferenceSource)
        );

        self::assertInstanceOf(WorkerEvent::class, $workerEvent);
        self::assertNotNull($workerEvent->getId());
        self::assertSame($expectedWorkerEvent->getType(), $workerEvent->getType());
        self::assertSame($expectedWorkerEvent->getReference(), $workerEvent->getReference());
        self::assertSame($expectedWorkerEvent->getPayload(), $workerEvent->getPayload());
    }

    /**
     * @return array<mixed>
     */
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
}
