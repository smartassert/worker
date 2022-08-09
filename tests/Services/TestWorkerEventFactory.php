<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Entity\WorkerEvent;
use App\Repository\WorkerEventReferenceRepository;
use App\Repository\WorkerEventRepository;
use App\Tests\Model\WorkerEventSetup;
use Doctrine\ORM\EntityManagerInterface;

class TestWorkerEventFactory
{
    public function __construct(
        private readonly WorkerEventRepository $workerEventRepository,
        private readonly WorkerEventReferenceRepository $workerEventReferenceRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function create(WorkerEventSetup $workerEventSetup): WorkerEvent
    {
        $workerEventReference = $workerEventSetup->getReference();
        $workerEventReferenceEntity = $this->workerEventReferenceRepository->findOneBy(
            $workerEventReference->toArray()
        );

        if (null === $workerEventReferenceEntity) {
            $workerEventReferenceEntity = $workerEventReference;
            $this->workerEventReferenceRepository->add($workerEventReferenceEntity);
            $this->entityManager->flush();
        }

        $workerEvent = $this->workerEventRepository->add(new WorkerEvent(
            $workerEventSetup->getScope(),
            $workerEventSetup->getOutcome(),
            $workerEventReferenceEntity,
            $workerEventSetup->getPayload()
        ));

        $workerEvent->setState($workerEventSetup->getState());
        $this->entityManager->flush();

        return $workerEvent;
    }
}
