<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Services\CallbackState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\CallbackSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestCallbackFactory;

class CallbackStateTest extends AbstractBaseFunctionalTest
{
    private CallbackState $callbackState;
    private TestCallbackFactory $testCallbackFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $callbackState = self::getContainer()->get(CallbackState::class);
        if ($callbackState instanceof CallbackState) {
            $this->callbackState = $callbackState;
        }

        $testCallbackFactory = self::getContainer()->get(TestCallbackFactory::class);
        \assert($testCallbackFactory instanceof TestCallbackFactory);
        $this->testCallbackFactory = $testCallbackFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
        }
    }

    /**
     * @dataProvider getDataProvider
     *
     * @param array<WorkerEvent::STATE_*> $callbackStates
     */
    public function testGet(array $callbackStates, string $expectedState): void
    {
        foreach ($callbackStates as $callbackState) {
            $this->createCallbackEntity($callbackState);
        }

        self::assertSame($expectedState, (string) $this->callbackState);
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'no callbacks' => [
                'callbackStates' => [],
                'expectedState' => CallbackState::STATE_AWAITING,
            ],
            'awaiting, sending, queued' => [
                'callbackStates' => [
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                ],
                'expectedState' => CallbackState::STATE_RUNNING,
            ],
            'awaiting, sending, queued, complete' => [
                'callbackStates' => [
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                    WorkerEvent::STATE_COMPLETE,
                ],
                'expectedState' => CallbackState::STATE_RUNNING,
            ],
            'awaiting, sending, queued, failed' => [
                'callbackStates' => [
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                    WorkerEvent::STATE_FAILED,
                ],
                'expectedState' => CallbackState::STATE_RUNNING,
            ],
            'two complete, three failed' => [
                'callbackStates' => [
                    WorkerEvent::STATE_COMPLETE,
                    WorkerEvent::STATE_COMPLETE,
                    WorkerEvent::STATE_FAILED,
                    WorkerEvent::STATE_FAILED,
                    WorkerEvent::STATE_FAILED,
                ],
                'expectedState' => CallbackState::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param array<WorkerEvent::STATE_*>   $callbackStates
     * @param array<CallbackState::STATE_*> $expectedIsStates
     * @param array<CallbackState::STATE_*> $expectedIsNotStates
     */
    public function testIs(array $callbackStates, array $expectedIsStates, array $expectedIsNotStates): void
    {
        foreach ($callbackStates as $callbackState) {
            $this->createCallbackEntity($callbackState);
        }

        self::assertTrue($this->callbackState->is(...$expectedIsStates));
        self::assertFalse($this->callbackState->is(...$expectedIsNotStates));
    }

    /**
     * @return array<mixed>
     */
    public function isDataProvider(): array
    {
        return [
            'no callbacks' => [
                'callbackStates' => [],
                'expectedIsStates' => [
                    CallbackState::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    CallbackState::STATE_RUNNING,
                    CallbackState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued' => [
                'callbackStates' => [
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                ],
                'expectedIsStates' => [
                    CallbackState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    CallbackState::STATE_AWAITING,
                    CallbackState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued, complete' => [
                'callbackStates' => [
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                    WorkerEvent::STATE_COMPLETE,
                ],
                'expectedIsStates' => [
                    CallbackState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    CallbackState::STATE_AWAITING,
                    CallbackState::STATE_COMPLETE,
                ],
            ],
            'awaiting, sending, queued, failed' => [
                'callbackStates' => [
                    WorkerEvent::STATE_AWAITING,
                    WorkerEvent::STATE_QUEUED,
                    WorkerEvent::STATE_SENDING,
                    WorkerEvent::STATE_FAILED,
                ],
                'expectedIsStates' => [
                    CallbackState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    CallbackState::STATE_AWAITING,
                    CallbackState::STATE_COMPLETE,
                ],
            ],
            'two complete, three failed' => [
                'callbackStates' => [
                    WorkerEvent::STATE_COMPLETE,
                    WorkerEvent::STATE_COMPLETE,
                    WorkerEvent::STATE_FAILED,
                    WorkerEvent::STATE_FAILED,
                    WorkerEvent::STATE_FAILED,
                ],
                'expectedIsStates' => [
                    CallbackState::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    CallbackState::STATE_AWAITING,
                    CallbackState::STATE_RUNNING,
                ],
            ],
        ];
    }

    /**
     * @param WorkerEvent::STATE_* $state
     */
    private function createCallbackEntity(string $state): WorkerEvent
    {
        $callbackSetup = (new CallbackSetup())->withState($state);

        return $this->testCallbackFactory->create($callbackSetup);
//        $entity = CallbackEntity::create(CallbackEntity::TYPE_STEP_PASSED, []);
//        $entity->setState($state);
//
//        $this->entityManager->persist($entity);
//        $this->entityManager->flush();
//
//        return $entity;
    }
}
