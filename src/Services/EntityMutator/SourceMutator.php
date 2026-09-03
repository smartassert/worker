<?php

declare(strict_types=1);

namespace App\Services\EntityMutator;

use App\Entity\Source;
use App\Event\ApplicationStateChangedEvent;
use App\Repository\SourceRepository;
use App\Services\ApplicationStateFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class SourceMutator
{
    public function __construct(
        private SourceRepository $repository,
        private ApplicationStateFactory $applicationStateFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function save(Source $entity): void
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
