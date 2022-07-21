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
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class ApplicationStateControllerTest extends AbstractBaseFunctionalTest
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
     * @dataProvider getDataProvider
     *
     * @param array<mixed> $expectedResponseData
     */
    public function testGet(EnvironmentSetup $setup, array $expectedResponseData): void
    {
        $this->environmentFactory->create($setup);

        $response = $this->clientRequestSender->getApplicationState();

        $this->jsonResponseAsserter->assertJsonResponse(200, $expectedResponseData, $response);
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(
                (new JobSetup())
                    ->withLabel('label content')
                    ->withEventDeliveryUrl('http://example.com/events')
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
            ]);

        return [
            'no job' => [
                'setup' => (new EnvironmentSetup()),
                'expectedResponseData' => [
                    'application' => ApplicationState::AWAITING_JOB->value,
                    'compilation' => CompilationState::AWAITING->value,
                    'event_delivery' => EventDeliveryState::AWAITING->value,
                    'execution' => ExecutionState::AWAITING->value,
                ],
            ],
            'compilation running' => [
                'setup' => $environmentSetup,
                'expectedResponseData' => [
                    'application' => ApplicationState::COMPILING->value,
                    'compilation' => CompilationState::RUNNING->value,
                    'event_delivery' => EventDeliveryState::AWAITING->value,
                    'execution' => ExecutionState::AWAITING->value,
                ],
            ],
            'compilation complete, execution awaiting' => [
                'setup' => $environmentSetup->withTestSetups([
                    (new TestSetup())->withSource('Test/test1.yml'),
                    (new TestSetup())->withSource('Test/test2.yml'),
                    (new TestSetup())->withSource('Test/test3.yml'),
                ]),
                'expectedResponseData' => [
                    'application' => ApplicationState::EXECUTING->value,
                    'compilation' => CompilationState::COMPLETE->value,
                    'event_delivery' => EventDeliveryState::AWAITING->value,
                    'execution' => ExecutionState::AWAITING->value,
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
                    'application' => ApplicationState::EXECUTING->value,
                    'compilation' => CompilationState::COMPLETE->value,
                    'event_delivery' => EventDeliveryState::AWAITING->value,
                    'execution' => ExecutionState::RUNNING->value,
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
                            ->withState(WorkerEventState::QUEUED)
                    ]),
                'expectedResponseData' => [
                    'application' => ApplicationState::EXECUTING->value,
                    'compilation' => CompilationState::COMPLETE->value,
                    'event_delivery' => EventDeliveryState::RUNNING->value,
                    'execution' => ExecutionState::RUNNING->value,
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
                            ->withState(WorkerEventState::QUEUED)
                    ]),
                'expectedResponseData' => [
                    'application' => ApplicationState::COMPLETING_EVENT_DELIVERY->value,
                    'compilation' => CompilationState::COMPLETE->value,
                    'event_delivery' => EventDeliveryState::RUNNING->value,
                    'execution' => ExecutionState::COMPLETE->value,
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
                    'application' => ApplicationState::COMPLETE->value,
                    'compilation' => CompilationState::COMPLETE->value,
                    'event_delivery' => EventDeliveryState::COMPLETE->value,
                    'execution' => ExecutionState::COMPLETE->value,
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
                    'application' => ApplicationState::COMPLETE->value,
                    'compilation' => CompilationState::COMPLETE->value,
                    'event_delivery' => EventDeliveryState::COMPLETE->value,
                    'execution' => ExecutionState::CANCELLED->value,
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
                    'application' => ApplicationState::TIMED_OUT->value,
                    'compilation' => CompilationState::COMPLETE->value,
                    'event_delivery' => EventDeliveryState::COMPLETE->value,
                    'execution' => ExecutionState::CANCELLED->value,
                ],
            ],
        ];
    }
}
