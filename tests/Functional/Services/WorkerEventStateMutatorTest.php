<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Services\WorkerEventStateMutator;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;
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

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(WorkerEventReference::class);
        }
    }

    /**
     * @dataProvider setQueuedDataProvider
     */
    public function testSetQueued(WorkerEventState $initialState, WorkerEventState $expectedState): void
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
            WorkerEventState::AWAITING->value => [
                'initialState' => WorkerEventState::AWAITING,
                'expectedState' => WorkerEventState::QUEUED,
            ],
            WorkerEventState::QUEUED->value => [
                'initialState' => WorkerEventState::QUEUED,
                'expectedState' => WorkerEventState::QUEUED,
            ],
            WorkerEventState::SENDING->value => [
                'initialState' => WorkerEventState::SENDING,
                'expectedState' => WorkerEventState::QUEUED,
            ],
            WorkerEventState::FAILED->value => [
                'initialState' => WorkerEventState::FAILED,
                'expectedState' => WorkerEventState::FAILED,
            ],
            WorkerEventState::COMPLETE->value => [
                'initialState' => WorkerEventState::COMPLETE,
                'expectedState' => WorkerEventState::COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setSendingDataProvider
     */
    public function testSetSending(WorkerEventState $initialState, WorkerEventState $expectedState): void
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
            WorkerEventState::AWAITING->value => [
                'initialState' => WorkerEventState::AWAITING,
                'expectedState' => WorkerEventState::AWAITING,
            ],
            WorkerEventState::QUEUED->value => [
                'initialState' => WorkerEventState::QUEUED,
                'expectedState' => WorkerEventState::SENDING,
            ],
            WorkerEventState::SENDING->value => [
                'initialState' => WorkerEventState::SENDING,
                'expectedState' => WorkerEventState::SENDING,
            ],
            WorkerEventState::FAILED->value => [
                'initialState' => WorkerEventState::FAILED,
                'expectedState' => WorkerEventState::FAILED,
            ],
            WorkerEventState::COMPLETE->value => [
                'initialState' => WorkerEventState::COMPLETE,
                'expectedState' => WorkerEventState::COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setFailedDataProvider
     */
    public function testSetFailed(WorkerEventState $initialState, WorkerEventState $expectedState): void
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
            WorkerEventState::AWAITING->value => [
                'initialState' => WorkerEventState::AWAITING,
                'expectedState' => WorkerEventState::AWAITING,
            ],
            WorkerEventState::QUEUED->value => [
                'initialState' => WorkerEventState::QUEUED,
                'expectedState' => WorkerEventState::FAILED,
            ],
            WorkerEventState::SENDING->value => [
                'initialState' => WorkerEventState::SENDING,
                'expectedState' => WorkerEventState::FAILED,
            ],
            WorkerEventState::FAILED->value => [
                'initialState' => WorkerEventState::FAILED,
                'expectedState' => WorkerEventState::FAILED,
            ],
            WorkerEventState::COMPLETE->value => [
                'initialState' => WorkerEventState::COMPLETE,
                'expectedState' => WorkerEventState::COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider setCompleteDataProvider
     */
    public function testSetComplete(WorkerEventState $initialState, WorkerEventState $expectedState): void
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
            WorkerEventState::AWAITING->value => [
                'initialState' => WorkerEventState::AWAITING,
                'expectedState' => WorkerEventState::AWAITING,
            ],
            WorkerEventState::QUEUED->value => [
                'initialState' => WorkerEventState::QUEUED,
                'expectedState' => WorkerEventState::QUEUED,
            ],
            WorkerEventState::SENDING->value => [
                'initialState' => WorkerEventState::SENDING,
                'expectedState' => WorkerEventState::COMPLETE,
            ],
            WorkerEventState::FAILED->value => [
                'initialState' => WorkerEventState::FAILED,
                'expectedState' => WorkerEventState::FAILED,
            ],
            WorkerEventState::COMPLETE->value => [
                'initialState' => WorkerEventState::COMPLETE,
                'expectedState' => WorkerEventState::COMPLETE,
            ],
        ];
    }

    private function doSetAsStateTest(
        WorkerEvent $workerEvent,
        WorkerEventState $initialState,
        WorkerEventState $expectedState,
        callable $setter
    ): void {
        $workerEvent->setState($initialState);

        $this->entityManager->persist($workerEvent->reference);
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
        return new WorkerEvent(
            WorkerEventScope::SOURCE_COMPILATION,
            WorkerEventOutcome::FAILED,
            new WorkerEventReference('non-empty label', 'non-empty reference'),
            []
        );
    }
}
