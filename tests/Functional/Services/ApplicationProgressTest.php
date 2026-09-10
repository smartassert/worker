<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\ApplicationState;
use App\Enum\TestState;
use App\Enum\WorkerEventState;
use App\Event\EmittableEvent\EventTypeInterface;
use App\Services\ApplicationProgress;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApplicationProgressTest extends WebTestCase
{
    private ApplicationProgress $applicationProgress;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $applicationProgress = self::getContainer()->get(ApplicationProgress::class);
        \assert($applicationProgress instanceof ApplicationProgress);
        $this->applicationProgress = $applicationProgress;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
        }
    }

    #[DataProvider('getDataProvider')]
    public function testGet(EnvironmentSetup $setup, ApplicationState $expected): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expected, $this->applicationProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        return [
            'no job, is awaiting' => [
                'setup' => new EnvironmentSetup(),
                'expected' => ApplicationState::AWAITING,
            ],
            'no sources compiled' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                        new SourceSetup()->withPath('Test/test2.yml'),
                    ]),
                'expected' => ApplicationState::COMPILING,
            ],
            'first source compiled' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                        new SourceSetup()->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        new TestSetup()->withSource('Test/test1.yml'),
                    ]),
                'expected' => ApplicationState::COMPILING,
            ],
            'all sources compiled, no tests running' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                        new SourceSetup()->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        new TestSetup()->withSource('Test/test1.yml'),
                        new TestSetup()->withSource('Test/test2.yml'),
                    ]),
                'expected' => ApplicationState::EXECUTING,
            ],
            'first test complete, no event deliveries' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                        new SourceSetup()->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()->withSource('Test/test2.yml'),
                    ]),
                'expected' => ApplicationState::EXECUTING,
            ],
            'first test complete, event delivery for first test complete' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                        new SourceSetup()->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()->withSource('Test/test2.yml'),
                    ])
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()->withState(WorkerEventState::COMPLETE),
                    ]),
                'expected' => ApplicationState::EXECUTING,
            ],
            'all tests complete, first event delivery complete, second event delivery running' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                        new SourceSetup()->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()->withState(WorkerEventState::COMPLETE),
                        new WorkerEventSetup()->withState(WorkerEventState::SENDING),
                    ]),
                'expected' => ApplicationState::COMPLETING_EVENT_DELIVERY,
            ],
            'all tests complete, all event deliveries complete' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                        new SourceSetup()->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                    ])
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withType('job/started')
                            ->withState(WorkerEventState::COMPLETE),
                        new WorkerEventSetup()
                            ->withType('job/ended')
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expected' => ApplicationState::COMPLETE,
            ],
            'has a job-timeout event delivery' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withType(EventTypeInterface::JOB_TIMED_OUT)
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expected' => ApplicationState::TIMED_OUT,
            ],
            'compilation failed' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                    ])
                    ->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withType(EventTypeInterface::COMPILATION_FAILED),
                    ]),
                'expected' => ApplicationState::FAILED,
            ],
            'execution failed' => [
                'setup' => new EnvironmentSetup()
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        new SourceSetup()->withPath('Test/test1.yml'),
                    ])
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::FAILED),
                    ])
                    ->withWorkerEventSetups([
                        new WorkerEventSetup(),
                    ]),
                'expected' => ApplicationState::FAILED,
            ],
        ];
    }
}
