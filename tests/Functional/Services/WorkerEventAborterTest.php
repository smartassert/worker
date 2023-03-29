<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventAborter;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\TestWorkerEventFactory;

class WorkerEventAborterTest extends AbstractBaseFunctionalTestCase
{
    private WorkerEventAborter $aborter;
    private WorkerEventRepository $workerEventRepository;

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
    }

    public function testAbort(): void
    {
        $testWorkerEventFactory = self::getContainer()->get(TestWorkerEventFactory::class);
        \assert($testWorkerEventFactory instanceof TestWorkerEventFactory);

        $workerEvent = $testWorkerEventFactory->create(
            (new WorkerEventSetup())
                ->withScope(WorkerEventScope::JOB)
                ->withOutcome(WorkerEventOutcome::COMPLETED)
                ->withReference(new WorkerEventReference('non-empty label', 'non-empty reference'))
                ->withState(WorkerEventState::QUEUED)
                ->withPayload([])
        );

        $id = $workerEvent->getId();

        self::assertIsInt($id);
        self::assertNotSame(WorkerEventState::FAILED, $workerEvent->getState());

        $this->aborter->abort($id);

        $workerEvent = $this->workerEventRepository->find($id);
        \assert($workerEvent instanceof WorkerEvent);

        self::assertSame(WorkerEventState::FAILED, $workerEvent->getState());
    }
}
