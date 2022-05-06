<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Services\CallbackStateMutator;
use App\Tests\AbstractBaseFunctionalTest;
use Doctrine\ORM\EntityManagerInterface;

class CallbackStateMutatorTest extends AbstractBaseFunctionalTest
{
    private CallbackStateMutator $callbackStateMutator;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $callbackStateMutator = self::getContainer()->get(CallbackStateMutator::class);
        \assert($callbackStateMutator instanceof CallbackStateMutator);
        $this->callbackStateMutator = $callbackStateMutator;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
    }

    /**
     * @dataProvider setQueuedDataProvider
     *
     * @param WorkerEvent::STATE_* $initialState
     * @param WorkerEvent::STATE_* $expectedState
     */
    public function testSetQueued(string $initialState, string $expectedState): void
    {
        foreach ($this->createCallbacks() as $callback) {
            $this->doSetAsStateTest(
                $callback,
                $initialState,
                $expectedState,
                function (WorkerEvent $callback) {
                    $this->callbackStateMutator->setQueued($callback);
                }
            );
        }
    }

    /**
     * @return array<mixed>
     */
    public function setQueuedDataProvider(): array
    {
        return [
            WorkerEvent::STATE_AWAITING => [
                'initialState' => WorkerEvent::STATE_AWAITING,
                'expectedState' => WorkerEvent::STATE_QUEUED,
            ],
            WorkerEvent::STATE_QUEUED => [
                'initialState' => WorkerEvent::STATE_QUEUED,
                'expectedState' => WorkerEvent::STATE_QUEUED,
            ],
            WorkerEvent::STATE_SENDING => [
                'initialState' => WorkerEvent::STATE_SENDING,
                'expectedState' => WorkerEvent::STATE_QUEUED,
            ],
            WorkerEvent::STATE_FAILED => [
                'initialState' => WorkerEvent::STATE_FAILED,
                'expectedState' => WorkerEvent::STATE_FAILED,
            ],
            WorkerEvent::STATE_COMPLETE => [
                'initialState' => WorkerEvent::STATE_COMPLETE,
                'expectedState' => WorkerEvent::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setSendingDataProvider
     *
     * @param WorkerEvent::STATE_* $initialState
     * @param WorkerEvent::STATE_* $expectedState
     */
    public function testSetSending(string $initialState, string $expectedState): void
    {
        foreach ($this->createCallbacks() as $callback) {
            $this->doSetAsStateTest(
                $callback,
                $initialState,
                $expectedState,
                function (WorkerEvent $callback) {
                    $this->callbackStateMutator->setSending($callback);
                }
            );
        }
    }

    /**
     * @return array<mixed>
     */
    public function setSendingDataProvider(): array
    {
        return [
            WorkerEvent::STATE_AWAITING => [
                'initialState' => WorkerEvent::STATE_AWAITING,
                'expectedState' => WorkerEvent::STATE_AWAITING,
            ],
            WorkerEvent::STATE_QUEUED => [
                'initialState' => WorkerEvent::STATE_QUEUED,
                'expectedState' => WorkerEvent::STATE_SENDING,
            ],
            WorkerEvent::STATE_SENDING => [
                'initialState' => WorkerEvent::STATE_SENDING,
                'expectedState' => WorkerEvent::STATE_SENDING,
            ],
            WorkerEvent::STATE_FAILED => [
                'initialState' => WorkerEvent::STATE_FAILED,
                'expectedState' => WorkerEvent::STATE_FAILED,
            ],
            WorkerEvent::STATE_COMPLETE => [
                'initialState' => WorkerEvent::STATE_COMPLETE,
                'expectedState' => WorkerEvent::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setFailedDataProvider
     *
     * @param WorkerEvent::STATE_* $initialState
     * @param WorkerEvent::STATE_* $expectedState
     */
    public function testSetFailed(string $initialState, string $expectedState): void
    {
        foreach ($this->createCallbacks() as $callback) {
            $this->doSetAsStateTest(
                $callback,
                $initialState,
                $expectedState,
                function (WorkerEvent $callback) {
                    $this->callbackStateMutator->setFailed($callback);
                }
            );
        }
    }

    /**
     * @return array<mixed>
     */
    public function setFailedDataProvider(): array
    {
        return [
            WorkerEvent::STATE_AWAITING => [
                'initialState' => WorkerEvent::STATE_AWAITING,
                'expectedState' => WorkerEvent::STATE_AWAITING,
            ],
            WorkerEvent::STATE_QUEUED => [
                'initialState' => WorkerEvent::STATE_QUEUED,
                'expectedState' => WorkerEvent::STATE_FAILED,
            ],
            WorkerEvent::STATE_SENDING => [
                'initialState' => WorkerEvent::STATE_SENDING,
                'expectedState' => WorkerEvent::STATE_FAILED,
            ],
            WorkerEvent::STATE_FAILED => [
                'initialState' => WorkerEvent::STATE_FAILED,
                'expectedState' => WorkerEvent::STATE_FAILED,
            ],
            WorkerEvent::STATE_COMPLETE => [
                'initialState' => WorkerEvent::STATE_COMPLETE,
                'expectedState' => WorkerEvent::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setCompleteDataProvider
     *
     * @param WorkerEvent::STATE_* $initialState
     * @param WorkerEvent::STATE_* $expectedState
     */
    public function testSetComplete(string $initialState, string $expectedState): void
    {
        foreach ($this->createCallbacks() as $callback) {
            $this->doSetAsStateTest(
                $callback,
                $initialState,
                $expectedState,
                function (WorkerEvent $callback) {
                    $this->callbackStateMutator->setComplete($callback);
                }
            );
        }
    }

    /**
     * @return array<mixed>
     */
    public function setCompleteDataProvider(): array
    {
        return [
            WorkerEvent::STATE_AWAITING => [
                'initialState' => WorkerEvent::STATE_AWAITING,
                'expectedState' => WorkerEvent::STATE_AWAITING,
            ],
            WorkerEvent::STATE_QUEUED => [
                'initialState' => WorkerEvent::STATE_QUEUED,
                'expectedState' => WorkerEvent::STATE_QUEUED,
            ],
            WorkerEvent::STATE_SENDING => [
                'initialState' => WorkerEvent::STATE_SENDING,
                'expectedState' => WorkerEvent::STATE_COMPLETE,
            ],
            WorkerEvent::STATE_FAILED => [
                'initialState' => WorkerEvent::STATE_FAILED,
                'expectedState' => WorkerEvent::STATE_FAILED,
            ],
            WorkerEvent::STATE_COMPLETE => [
                'initialState' => WorkerEvent::STATE_COMPLETE,
                'expectedState' => WorkerEvent::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setSendingDataProvider
     *
     * @param WorkerEvent::STATE_* $initialState
     * @param WorkerEvent::STATE_* $expectedState
     */
    private function doSetAsStateTest(
        WorkerEvent $callback,
        string $initialState,
        string $expectedState,
        callable $setter
    ): void {
        $callback->setState($initialState);

        $this->entityManager->persist($callback);
        $this->entityManager->flush();

        self::assertSame($initialState, $callback->getState());

        $setter($callback);

        self::assertSame($expectedState, $callback->getState());
    }

    /**
     * @return WorkerEvent[]
     */
    private function createCallbacks(): array
    {
        return [
            'default entity' => $this->createCallbackEntity(),
        ];
    }

    private function createCallbackEntity(): WorkerEvent
    {
        return WorkerEvent::create(
            WorkerEvent::TYPE_COMPILATION_FAILED,
            'non-empty reference',
            []
        );
    }
}
