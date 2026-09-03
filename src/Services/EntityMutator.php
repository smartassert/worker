<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Event\ApplicationStateChangedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class EntityMutator
{
    public function __construct(
        private ApplicationStateFactory $applicationStateFactory,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $entityManager,
    ) {}

    public function save(Job|Source|Test|WorkerEvent|WorkerEventReference $entity): void
    {
        $preSaveState = $this->applicationStateFactory->create();

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $postSaveState = $this->applicationStateFactory->create();

        if (false === $postSaveState->equals($preSaveState)) {
            $this->eventDispatcher->dispatch(
                new ApplicationStateChangedEvent($preSaveState, $postSaveState)
            );
        }
    }
}
