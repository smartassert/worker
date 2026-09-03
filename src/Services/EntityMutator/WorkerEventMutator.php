<?php

declare(strict_types=1);

namespace App\Services\EntityMutator;

use App\Entity\WorkerEvent;
use App\Event\ApplicationStateChangedEvent;
use App\Repository\WorkerEventRepository;
use App\Services\ApplicationStateFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class WorkerEventMutator
{
    public function __construct(
        private WorkerEventRepository $repository,
        private ApplicationStateFactory $applicationStateFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function save(WorkerEvent $entity): void
    {
        $preSaveState = $this->applicationStateFactory->create();
        $this->repository->add($entity);
        $postSaveState = $this->applicationStateFactory->create();

        if (false === $postSaveState->equals($preSaveState)) {
            $this->eventDispatcher->dispatch(
                new ApplicationStateChangedEvent($preSaveState, $postSaveState)
            );
        }
    }
}
