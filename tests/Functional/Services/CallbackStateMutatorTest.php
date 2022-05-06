<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Callback\CallbackEntity;
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
     * @param CallbackEntity::STATE_* $initialState
     * @param CallbackEntity::STATE_* $expectedState
     */
    public function testSetQueued(string $initialState, string $expectedState): void
    {
        foreach ($this->createCallbacks() as $callback) {
            $this->doSetAsStateTest(
                $callback,
                $initialState,
                $expectedState,
                function (CallbackEntity $callback) {
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
            CallbackEntity::STATE_AWAITING => [
                'initialState' => CallbackEntity::STATE_AWAITING,
                'expectedState' => CallbackEntity::STATE_QUEUED,
            ],
            CallbackEntity::STATE_QUEUED => [
                'initialState' => CallbackEntity::STATE_QUEUED,
                'expectedState' => CallbackEntity::STATE_QUEUED,
            ],
            CallbackEntity::STATE_SENDING => [
                'initialState' => CallbackEntity::STATE_SENDING,
                'expectedState' => CallbackEntity::STATE_QUEUED,
            ],
            CallbackEntity::STATE_FAILED => [
                'initialState' => CallbackEntity::STATE_FAILED,
                'expectedState' => CallbackEntity::STATE_FAILED,
            ],
            CallbackEntity::STATE_COMPLETE => [
                'initialState' => CallbackEntity::STATE_COMPLETE,
                'expectedState' => CallbackEntity::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setSendingDataProvider
     *
     * @param CallbackEntity::STATE_* $initialState
     * @param CallbackEntity::STATE_* $expectedState
     */
    public function testSetSending(string $initialState, string $expectedState): void
    {
        foreach ($this->createCallbacks() as $callback) {
            $this->doSetAsStateTest(
                $callback,
                $initialState,
                $expectedState,
                function (CallbackEntity $callback) {
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
            CallbackEntity::STATE_AWAITING => [
                'initialState' => CallbackEntity::STATE_AWAITING,
                'expectedState' => CallbackEntity::STATE_AWAITING,
            ],
            CallbackEntity::STATE_QUEUED => [
                'initialState' => CallbackEntity::STATE_QUEUED,
                'expectedState' => CallbackEntity::STATE_SENDING,
            ],
            CallbackEntity::STATE_SENDING => [
                'initialState' => CallbackEntity::STATE_SENDING,
                'expectedState' => CallbackEntity::STATE_SENDING,
            ],
            CallbackEntity::STATE_FAILED => [
                'initialState' => CallbackEntity::STATE_FAILED,
                'expectedState' => CallbackEntity::STATE_FAILED,
            ],
            CallbackEntity::STATE_COMPLETE => [
                'initialState' => CallbackEntity::STATE_COMPLETE,
                'expectedState' => CallbackEntity::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setFailedDataProvider
     *
     * @param CallbackEntity::STATE_* $initialState
     * @param CallbackEntity::STATE_* $expectedState
     */
    public function testSetFailed(string $initialState, string $expectedState): void
    {
        foreach ($this->createCallbacks() as $callback) {
            $this->doSetAsStateTest(
                $callback,
                $initialState,
                $expectedState,
                function (CallbackEntity $callback) {
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
            CallbackEntity::STATE_AWAITING => [
                'initialState' => CallbackEntity::STATE_AWAITING,
                'expectedState' => CallbackEntity::STATE_AWAITING,
            ],
            CallbackEntity::STATE_QUEUED => [
                'initialState' => CallbackEntity::STATE_QUEUED,
                'expectedState' => CallbackEntity::STATE_FAILED,
            ],
            CallbackEntity::STATE_SENDING => [
                'initialState' => CallbackEntity::STATE_SENDING,
                'expectedState' => CallbackEntity::STATE_FAILED,
            ],
            CallbackEntity::STATE_FAILED => [
                'initialState' => CallbackEntity::STATE_FAILED,
                'expectedState' => CallbackEntity::STATE_FAILED,
            ],
            CallbackEntity::STATE_COMPLETE => [
                'initialState' => CallbackEntity::STATE_COMPLETE,
                'expectedState' => CallbackEntity::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setCompleteDataProvider
     *
     * @param CallbackEntity::STATE_* $initialState
     * @param CallbackEntity::STATE_* $expectedState
     */
    public function testSetComplete(string $initialState, string $expectedState): void
    {
        foreach ($this->createCallbacks() as $callback) {
            $this->doSetAsStateTest(
                $callback,
                $initialState,
                $expectedState,
                function (CallbackEntity $callback) {
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
            CallbackEntity::STATE_AWAITING => [
                'initialState' => CallbackEntity::STATE_AWAITING,
                'expectedState' => CallbackEntity::STATE_AWAITING,
            ],
            CallbackEntity::STATE_QUEUED => [
                'initialState' => CallbackEntity::STATE_QUEUED,
                'expectedState' => CallbackEntity::STATE_QUEUED,
            ],
            CallbackEntity::STATE_SENDING => [
                'initialState' => CallbackEntity::STATE_SENDING,
                'expectedState' => CallbackEntity::STATE_COMPLETE,
            ],
            CallbackEntity::STATE_FAILED => [
                'initialState' => CallbackEntity::STATE_FAILED,
                'expectedState' => CallbackEntity::STATE_FAILED,
            ],
            CallbackEntity::STATE_COMPLETE => [
                'initialState' => CallbackEntity::STATE_COMPLETE,
                'expectedState' => CallbackEntity::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setSendingDataProvider
     *
     * @param CallbackEntity::STATE_* $initialState
     * @param CallbackEntity::STATE_* $expectedState
     */
    private function doSetAsStateTest(
        CallbackEntity $callback,
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
     * @return CallbackEntity[]
     */
    private function createCallbacks(): array
    {
        return [
            'default entity' => $this->createCallbackEntity(),
        ];
    }

    private function createCallbackEntity(): CallbackEntity
    {
        return CallbackEntity::create(
            CallbackEntity::TYPE_COMPILATION_FAILED,
            'non-empty reference',
            []
        );
    }
}
