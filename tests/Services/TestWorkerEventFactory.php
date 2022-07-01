<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\WorkerEvent;
use App\Repository\WorkerEventRepository;
use App\Tests\Model\WorkerEventSetup;
use Doctrine\ORM\EntityManagerInterface;

class TestWorkerEventFactory
{
    public function __construct(
        private readonly WorkerEventRepository $workerEventRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function create(WorkerEventSetup $workerEventSetup): WorkerEvent
    {
        $workerEvent = $this->workerEventRepository->add(new WorkerEvent(
            $workerEventSetup->getScope(),
            $workerEventSetup->getOutcome(),
            $workerEventSetup->getLabel(),
            'non-empty reference',
            $workerEventSetup->getPayload()
        ));

        $workerEvent->setState($workerEventSetup->getState());
        $this->entityManager->flush();

        return $workerEvent;
    }
}
