<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Services\WorkerEventStateMutator;
use App\Tests\AbstractBaseFunctionalTest;
use Doctrine\ORM\EntityManagerInterface;

class WorkerEventStateMutatorTest extends AbstractBaseFunctionalTest
{
    private WorkerEventStateMutator $stateMutator;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $stateMutator = self::getContainer()->get(WorkerEventStateMutator::class);
        \assert($stateMutator instanceof WorkerEventStateMutator);
        $this->stateMutator = $stateMutator;

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
        foreach ($this->createEntities() as $workerEvent) {
            $this->doSetAsStateTest(
                $workerEvent,
                $initialState,
                $expectedState,
                function (WorkerEvent $workerEvent) {
                    $this->stateMutator->setQueued($workerEvent);
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
        foreach ($this->createEntities() as $workerEvent) {
            $this->doSetAsStateTest(
                $workerEvent,
                $initialState,
                $expectedState,
                function (WorkerEvent $workerEvent) {
                    $this->stateMutator->setSending($workerEvent);
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
        foreach ($this->createEntities() as $workerEvent) {
            $this->doSetAsStateTest(
                $workerEvent,
                $initialState,
                $expectedState,
                function (WorkerEvent $workerEvent) {
                    $this->stateMutator->setFailed($workerEvent);
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
        foreach ($this->createEntities() as $workerEvent) {
            $this->doSetAsStateTest(
                $workerEvent,
                $initialState,
                $expectedState,
                function (WorkerEvent $workerEvent) {
                    $this->stateMutator->setComplete($workerEvent);
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
        WorkerEvent $workerEvent,
        string $initialState,
        string $expectedState,
        callable $setter
    ): void {
        $workerEvent->setState($initialState);

        $this->entityManager->persist($workerEvent);
        $this->entityManager->flush();

        self::assertSame($initialState, $workerEvent->getState());

        $setter($workerEvent);

        self::assertSame($expectedState, $workerEvent->getState());
    }

    /**
     * @return WorkerEvent[]
     */
    private function createEntities(): array
    {
        return [
            'default entity' => $this->createEntity(),
        ];
    }

    private function createEntity(): WorkerEvent
    {
        return WorkerEvent::create(
            WorkerEvent::TYPE_COMPILATION_FAILED,
            'non-empty reference',
            []
        );
    }
}
