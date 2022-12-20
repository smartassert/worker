<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Test as TestEntity;
use App\Enum\ExecutionExceptionScope;
use App\Enum\JobEndState;
use App\Enum\WorkerEventOutcome;
use App\Event\JobCompletedEvent;
use App\Event\JobTimeoutEmittableEvent;
use App\Event\SourceCompilationFailedEvent;
use App\Event\TestEmittableEvent;
use App\Model\Document\Exception;
use App\Model\Document\Test as TestDocument;
use App\Repository\JobRepository;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

class JobEndStateSetterTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private EventDispatcherInterface $eventDispatcher;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        assert($entityRemover instanceof EntityRemover);
        $entityRemover->removeForEntity(Job::class);

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $environmentFactory->create((new EnvironmentSetup())->withJobSetup(new JobSetup()));

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);

        $job = $jobRepository->get();
        \assert($job instanceof Job);
        $this->job = $job;
    }

    /**
     * @dataProvider subscribesToEventDataProvider
     */
    public function testSubscribesToEvent(Event $event, JobEndState $expectedJobEndState): void
    {
        $this->eventDispatcher->dispatch($event);
        self::assertSame($expectedJobEndState, $this->job->endState);
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToEventDataProvider(): array
    {
        $testEntity = new TestEntity('chrome', 'http://example.com', 'test.yml', '/', [], 0);

        return [
            'test/failed' => [
                'event' => new TestEmittableEvent(
                    $testEntity,
                    new TestDocument('test.yml', []),
                    'test.yml',
                    WorkerEventOutcome::FAILED
                ),
                'expectedJobEndState' => JobEndState::FAILED_TEST_FAILURE,
            ],
            'test/exception' => [
                'event' => new TestEmittableEvent(
                    $testEntity,
                    new Exception(ExecutionExceptionScope::TEST, []),
                    'test.yml',
                    WorkerEventOutcome::EXCEPTION
                ),
                'expectedJobEndState' => JobEndState::FAILED_TEST_EXCEPTION,
            ],
            'job/timeout' => [
                'event' => new JobTimeoutEmittableEvent('job label', 1000),
                'expectedJobEndState' => JobEndState::TIMED_OUT,
            ],
            'compilation/failed' => [
                'event' => new SourceCompilationFailedEvent('test.yml', []),
                'expectedJobEndState' => JobEndState::FAILED_COMPILATION,
            ],
            'job/completed' => [
                'event' => new JobCompletedEvent(),
                'expectedJobEndState' => JobEndState::COMPLETE,
            ],
        ];
    }
}
