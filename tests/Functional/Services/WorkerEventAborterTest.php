<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventAborter;
use App\Services\WorkerEventStateMutator;
use App\Tests\AbstractBaseFunctionalTest;

class WorkerEventAborterTest extends AbstractBaseFunctionalTest
{
    private WorkerEventAborter $aborter;
    private WorkerEventRepository $callbackRepository;
    private WorkerEventStateMutator $stateMutator;

    protected function setUp(): void
    {
        parent::setUp();

        $aborter = self::getContainer()->get(WorkerEventAborter::class);
        \assert($aborter instanceof WorkerEventAborter);
        $this->aborter = $aborter;

        $callbackRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($callbackRepository instanceof WorkerEventRepository);
        $this->callbackRepository = $callbackRepository;

        $repository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($repository instanceof WorkerEventRepository);
        $this->callbackRepository = $repository;

        $stateMutator = self::getContainer()->get(WorkerEventStateMutator::class);
        \assert($stateMutator instanceof WorkerEventStateMutator);
        $this->stateMutator = $stateMutator;
    }

    public function testAbort(): void
    {
        $callback = $this->callbackRepository->create(
            WorkerEvent::TYPE_JOB_COMPLETED,
            'non-empty reference',
            []
        );
        $this->stateMutator->setQueued($callback);

        $id = $callback->getId();

        self::assertIsInt($id);
        self::assertNotSame(WorkerEvent::STATE_FAILED, $callback->getState());

        $this->aborter->abort($id);

        $callback = $this->callbackRepository->find($id);
        \assert($callback instanceof WorkerEvent);

        self::assertSame(WorkerEvent::STATE_FAILED, $callback->getState());
    }
}
