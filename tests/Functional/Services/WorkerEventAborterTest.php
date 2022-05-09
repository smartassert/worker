<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\WorkerEvent;
use App\Repository\CallbackRepository;
use App\Services\WorkerEventAborter;
use App\Services\WorkerEventStateMutator;
use App\Tests\AbstractBaseFunctionalTest;

class WorkerEventAborterTest extends AbstractBaseFunctionalTest
{
    private WorkerEventAborter $aborter;
    private CallbackRepository $callbackRepository;
    private WorkerEventStateMutator $stateMutator;

    protected function setUp(): void
    {
        parent::setUp();

        $aborter = self::getContainer()->get(WorkerEventAborter::class);
        \assert($aborter instanceof WorkerEventAborter);
        $this->aborter = $aborter;

        $callbackRepository = self::getContainer()->get(CallbackRepository::class);
        \assert($callbackRepository instanceof CallbackRepository);
        $this->callbackRepository = $callbackRepository;

        $repository = self::getContainer()->get(CallbackRepository::class);
        \assert($repository instanceof CallbackRepository);
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
