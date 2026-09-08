<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\ApplicationState;
use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Enum\WorkerEventState;
use App\Event\EmittableEvent\EventTypeInterface;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApplicationStateControllerTest extends WebTestCase
{
    private ClientRequestSender $clientRequestSender;
    private EnvironmentFactory $environmentFactory;
    private JsonResponseAsserter $jsonResponseAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $clientRequestSender = self::getContainer()->get(ClientRequestSender::class);
        \assert($clientRequestSender instanceof ClientRequestSender);
        $this->clientRequestSender = $clientRequestSender;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $jsonResponseAsserter = self::getContainer()->get(JsonResponseAsserter::class);
        \assert($jsonResponseAsserter instanceof JsonResponseAsserter);
        $this->jsonResponseAsserter = $jsonResponseAsserter;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
            $entityRemover->removeForEntity(Job::class);
        }
    }

    /**
     * @param array<mixed> $expectedResponseData
     */
    #[DataProvider('getDataProvider')]
    public function testGet(EnvironmentSetup $setup, array $expectedResponseData): void
    {
        $this->environmentFactory->create($setup);

        $response = $this->clientRequestSender->getApplicationState();

        $this->jsonResponseAsserter->assertJsonResponse(200, $expectedResponseData, $response);
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        $environmentSetup = new EnvironmentSetup()
            ->withJobSetup(
                new JobSetup()
                    ->withLabel('label content')
                    ->withMaximumDurationInSeconds(11)
                    ->withTestPaths([
                        'Test/test1.yml',
                        'Test/test2.yml',
                        'Test/test3.yml',
                    ])
            )->withSourceSetups([
                new SourceSetup()->withPath('Test/test1.yml'),
                new SourceSetup()->withPath('Test/test2.yml'),
                new SourceSetup()->withPath('Test/test3.yml'),
            ])
        ;

        return [
            'no job' => [
                'setup' => (new EnvironmentSetup()),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                        ],
                    ],
                ],
            ],
            'compilation running' => [
                'setup' => $environmentSetup,
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::COMPILING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                            ApplicationState::COMPILING->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::RUNNING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                            CompilationState::RUNNING->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                        ],
                    ],
                ],
            ],
            'compilation complete, execution awaiting' => [
                'setup' => $environmentSetup->withTestSetups([
                    new TestSetup()->withSource('Test/test1.yml'),
                    new TestSetup()->withSource('Test/test2.yml'),
                    new TestSetup()->withSource('Test/test3.yml'),
                ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::EXECUTING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                            ApplicationState::COMPILING->value,
                            ApplicationState::EXECUTING->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                            CompilationState::RUNNING->value,
                            CompilationState::COMPLETE->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                        ],
                    ],
                ],
            ],
            'execution running, event delivery awaiting' => [
                'setup' => $environmentSetup->withTestSetups([
                    new TestSetup()
                        ->withSource('Test/test1.yml')
                        ->withState(TestState::COMPLETE),
                    new TestSetup()->withSource('Test/test2.yml'),
                    new TestSetup()->withSource('Test/test3.yml'),
                ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::EXECUTING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                            ApplicationState::COMPILING->value,
                            ApplicationState::EXECUTING->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                            CompilationState::RUNNING->value,
                            CompilationState::COMPLETE->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::AWAITING->value,
                        'meta_state' => [
                            'pending' => true,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::RUNNING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                            ExecutionState::RUNNING->value,
                        ],
                    ],
                ],
            ],
            'execution running, event delivery running' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()->withSource('Test/test2.yml'),
                        new TestSetup()->withSource('Test/test3.yml'),
                    ])->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withState(WorkerEventState::QUEUED),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::EXECUTING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                            ApplicationState::COMPILING->value,
                            ApplicationState::EXECUTING->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                            CompilationState::RUNNING->value,
                            CompilationState::COMPLETE->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::RUNNING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                            EventDeliveryState::RUNNING->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::RUNNING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                            ExecutionState::RUNNING->value,
                        ],
                    ],
                ],
            ],
            'execution complete, event delivery running' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()
                            ->withSource('Test/test3.yml')
                            ->withState(TestState::COMPLETE),
                    ])->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withState(WorkerEventState::QUEUED),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::COMPLETING_EVENT_DELIVERY->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                            ApplicationState::COMPILING->value,
                            ApplicationState::EXECUTING->value,
                            ApplicationState::COMPLETING_EVENT_DELIVERY->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                            CompilationState::RUNNING->value,
                            CompilationState::COMPLETE->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::RUNNING->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => false,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                            EventDeliveryState::RUNNING->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                            ExecutionState::RUNNING->value,
                            ExecutionState::COMPLETE->value,
                        ],
                    ],
                ],
            ],
            'execution complete, event delivery complete' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()
                            ->withSource('Test/test3.yml')
                            ->withState(TestState::COMPLETE),
                    ])->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                            ApplicationState::COMPILING->value,
                            ApplicationState::EXECUTING->value,
                            ApplicationState::COMPLETING_EVENT_DELIVERY->value,
                            ApplicationState::COMPLETE->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                            CompilationState::RUNNING->value,
                            CompilationState::COMPLETE->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                            EventDeliveryState::RUNNING->value,
                            EventDeliveryState::COMPLETE->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                            ExecutionState::RUNNING->value,
                            ExecutionState::COMPLETE->value,
                        ],
                    ],
                ],
            ],
            'execution failed, event delivery complete' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                        new TestSetup()
                            ->withSource('Test/test3.yml')
                            ->withState(TestState::FAILED),
                    ])->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::FAILED->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                            ApplicationState::COMPILING->value,
                            ApplicationState::EXECUTING->value,
                            ApplicationState::COMPLETING_EVENT_DELIVERY->value,
                            ApplicationState::FAILED->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                            CompilationState::RUNNING->value,
                            CompilationState::COMPLETE->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                            EventDeliveryState::RUNNING->value,
                            EventDeliveryState::COMPLETE->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::CANCELLED->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                            ExecutionState::RUNNING->value,
                            ExecutionState::CANCELLED->value,
                        ],
                    ],
                ],
            ],
            'timed out' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        new TestSetup()
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::FAILED),
                        new TestSetup()
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::CANCELLED),
                        new TestSetup()
                            ->withSource('Test/test3.yml')
                            ->withState(TestState::CANCELLED),
                    ])->withWorkerEventSetups([
                        new WorkerEventSetup()
                            ->withState(WorkerEventState::COMPLETE),
                        new WorkerEventSetup()
                            ->withType(EventTypeInterface::JOB_TIMED_OUT)
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::TIMED_OUT->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ApplicationState::AWAITING->value,
                            ApplicationState::COMPILING->value,
                            ApplicationState::EXECUTING->value,
                            ApplicationState::COMPLETING_EVENT_DELIVERY->value,
                            ApplicationState::TIMED_OUT->value,
                        ],
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            CompilationState::AWAITING->value,
                            CompilationState::RUNNING->value,
                            CompilationState::COMPLETE->value,
                        ],
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::COMPLETE->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => true,
                        ],
                        'previous_states' => [
                            EventDeliveryState::AWAITING->value,
                            EventDeliveryState::RUNNING->value,
                            EventDeliveryState::COMPLETE->value,
                        ],
                    ],
                    'execution' => [
                        'state' => ExecutionState::CANCELLED->value,
                        'meta_state' => [
                            'pending' => false,
                            'ended' => true,
                            'succeeded' => false,
                        ],
                        'previous_states' => [
                            ExecutionState::AWAITING->value,
                            ExecutionState::RUNNING->value,
                            ExecutionState::CANCELLED->value,
                        ],
                    ],
                ],
            ],
        ];
    }
}
