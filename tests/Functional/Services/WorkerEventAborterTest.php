<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventState;
use App\Entity\WorkerEventType;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventAborter;
use App\Services\WorkerEventStateMutator;
use App\Tests\AbstractBaseFunctionalTest;

class WorkerEventAborterTest extends AbstractBaseFunctionalTest
{
    private WorkerEventAborter $aborter;
    private WorkerEventRepository $workerEventRepository;
    private WorkerEventStateMutator $stateMutator;

    protected function setUp(): void
    {
        parent::setUp();

        $aborter = self::getContainer()->get(WorkerEventAborter::class);
        \assert($aborter instanceof WorkerEventAborter);
        $this->aborter = $aborter;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $repository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($repository instanceof WorkerEventRepository);
        $this->workerEventRepository = $repository;

        $stateMutator = self::getContainer()->get(WorkerEventStateMutator::class);
        \assert($stateMutator instanceof WorkerEventStateMutator);
        $this->stateMutator = $stateMutator;
    }

    public function testAbort(): void
    {
        $workerEvent = $this->workerEventRepository->create(
            WorkerEventType::JOB_COMPLETED,
            'non-empty reference',
            []
        );
        $this->stateMutator->setQueued($workerEvent);

        $id = $workerEvent->getId();

        self::assertIsInt($id);
        self::assertNotSame(WorkerEventState::FAILED, $workerEvent->getState());

        $this->aborter->abort($id);

        $workerEvent = $this->workerEventRepository->find($id);
        \assert($workerEvent instanceof WorkerEvent);

        self::assertSame(WorkerEventState::FAILED, $workerEvent->getState());
    }
}
