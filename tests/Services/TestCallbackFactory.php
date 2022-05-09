<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\WorkerEvent;
use App\Repository\WorkerEventRepository;
use App\Tests\Model\WorkerEventSetup;
use Doctrine\ORM\EntityManagerInterface;

class TestCallbackFactory
{
    public function __construct(
        private readonly WorkerEventRepository $workerEventRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function create(WorkerEventSetup $workerEventSetup): WorkerEvent
    {
        $workerEvent = $this->workerEventRepository->create(
            $workerEventSetup->getType(),
            'non-empty reference',
            $workerEventSetup->getPayload()
        );

        $workerEvent->setState($workerEventSetup->getState());
        $this->entityManager->flush();

        return $workerEvent;
    }
}
