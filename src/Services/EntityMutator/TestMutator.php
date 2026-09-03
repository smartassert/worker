<?php

declare(strict_types=1);

namespace App\Services\EntityMutator;

use App\Entity\Test;
use App\Event\ApplicationStateChangedEvent;
use App\Repository\TestRepository;
use App\Services\ApplicationStateFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class TestMutator
{
    public function __construct(
        private TestRepository $repository,
        private ApplicationStateFactory $applicationStateFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function save(Test $entity): void
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
