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
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
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
        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(
                (new JobSetup())
                    ->withLabel('label content')
                    ->withMaximumDurationInSeconds(11)
                    ->withTestPaths([
                        'Test/test1.yml',
                        'Test/test2.yml',
                        'Test/test3.yml',
                    ])
            )->withSourceSetups([
                (new SourceSetup())->withPath('Test/test1.yml'),
                (new SourceSetup())->withPath('Test/test2.yml'),
                (new SourceSetup())->withPath('Test/test3.yml'),
            ])
        ;

        return [
            'no job' => [
                'setup' => (new EnvironmentSetup()),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::AWAITING_JOB->value,
                        'is_end_state' => false,
                    ],
                    'compilation' => [
                        'state' => CompilationState::AWAITING->value,
                        'is_end_state' => false,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::AWAITING->value,
                        'is_end_state' => false,
                    ],
                    'execution' => [
                        'state' => ExecutionState::AWAITING->value,
                        'is_end_state' => false,
                    ],
                ],
            ],
            'compilation running' => [
                'setup' => $environmentSetup,
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::COMPILING->value,
                        'is_end_state' => false,
                    ],
                    'compilation' => [
                        'state' => CompilationState::RUNNING->value,
                        'is_end_state' => false,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::AWAITING->value,
                        'is_end_state' => false,
                    ],
                    'execution' => [
                        'state' => ExecutionState::AWAITING->value,
                        'is_end_state' => false,
                    ],
                ],
            ],
            'compilation complete, execution awaiting' => [
                'setup' => $environmentSetup->withTestSetups([
                    (new TestSetup())->withSource('Test/test1.yml'),
                    (new TestSetup())->withSource('Test/test2.yml'),
                    (new TestSetup())->withSource('Test/test3.yml'),
                ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::EXECUTING->value,
                        'is_end_state' => false,
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::AWAITING->value,
                        'is_end_state' => false,
                    ],
                    'execution' => [
                        'state' => ExecutionState::AWAITING->value,
                        'is_end_state' => false,
                    ],
                ],
            ],
            'execution running, event delivery awaiting' => [
                'setup' => $environmentSetup->withTestSetups([
                    (new TestSetup())
                        ->withSource('Test/test1.yml')
                        ->withState(TestState::COMPLETE),
                    (new TestSetup())->withSource('Test/test2.yml'),
                    (new TestSetup())->withSource('Test/test3.yml'),
                ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::EXECUTING->value,
                        'is_end_state' => false,
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::AWAITING->value,
                        'is_end_state' => false,
                    ],
                    'execution' => [
                        'state' => ExecutionState::RUNNING->value,
                        'is_end_state' => false,
                    ],
                ],
            ],
            'execution running, event delivery running' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())->withSource('Test/test2.yml'),
                        (new TestSetup())->withSource('Test/test3.yml'),
                    ])->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withState(WorkerEventState::QUEUED),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::EXECUTING->value,
                        'is_end_state' => false,
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::RUNNING->value,
                        'is_end_state' => false,
                    ],
                    'execution' => [
                        'state' => ExecutionState::RUNNING->value,
                        'is_end_state' => false,
                    ],
                ],
            ],
            'execution complete, event delivery running' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())
                            ->withSource('Test/test3.yml')
                            ->withState(TestState::COMPLETE),
                    ])->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withState(WorkerEventState::QUEUED),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::COMPLETING_EVENT_DELIVERY->value,
                        'is_end_state' => false,
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::RUNNING->value,
                        'is_end_state' => false,
                    ],
                    'execution' => [
                        'state' => ExecutionState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                ],
            ],
            'execution complete, event delivery complete' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())
                            ->withSource('Test/test3.yml')
                            ->withState(TestState::COMPLETE),
                    ])->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'execution' => [
                        'state' => ExecutionState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                ],
            ],
            'execution failed, event delivery complete' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::COMPLETE),
                        (new TestSetup())
                            ->withSource('Test/test3.yml')
                            ->withState(TestState::FAILED),
                    ])->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'execution' => [
                        'state' => ExecutionState::CANCELLED->value,
                        'is_end_state' => true,
                    ],
                ],
            ],
            'timed out' => [
                'setup' => $environmentSetup
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/test1.yml')
                            ->withState(TestState::FAILED),
                        (new TestSetup())
                            ->withSource('Test/test2.yml')
                            ->withState(TestState::CANCELLED),
                        (new TestSetup())
                            ->withSource('Test/test3.yml')
                            ->withState(TestState::CANCELLED),
                    ])->withWorkerEventSetups([
                        (new WorkerEventSetup())
                            ->withState(WorkerEventState::COMPLETE),
                        (new WorkerEventSetup())
                            ->withOutcome(WorkerEventOutcome::TIME_OUT)
                            ->withScope(WorkerEventScope::JOB)
                            ->withState(WorkerEventState::COMPLETE),
                    ]),
                'expectedResponseData' => [
                    'application' => [
                        'state' => ApplicationState::TIMED_OUT->value,
                        'is_end_state' => true,
                    ],
                    'compilation' => [
                        'state' => CompilationState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'event_delivery' => [
                        'state' => EventDeliveryState::COMPLETE->value,
                        'is_end_state' => true,
                    ],
                    'execution' => [
                        'state' => ExecutionState::CANCELLED->value,
                        'is_end_state' => true,
                    ],
                ],
            ],
        ];
    }
}
