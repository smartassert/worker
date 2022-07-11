<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Event\EventInterface;
use App\Event\JobTimeoutEvent;
use App\Event\StepEvent;
use App\Repository\TestRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestCanceller implements EventSubscriberInterface
{
    public function __construct(
        private TestStateMutator $testStateMutator,
        private TestRepository $testRepository
    ) {
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StepEvent::class => [
                ['cancelAwaitingFromTestFailureEvent', 0],
            ],
            JobTimeoutEvent::class => [
                ['cancelUnfinished', 0],
            ],
        ];
    }

    public function cancelAwaitingFromTestFailureEvent(EventInterface $event): void
    {
        if (
            WorkerEventScope::STEP !== $event->getScope()
            || !in_array($event->getOutcome(), [WorkerEventOutcome::FAILED, WorkerEventOutcome::EXCEPTION])
        ) {
            return;
        }

        $this->cancelAwaiting();
    }

    public function cancelUnfinished(): void
    {
        $this->cancelCollection($this->testRepository->findBy(['state' => TestState::getUnfinishedValues()]));
    }

    public function cancelAwaiting(): void
    {
        $this->cancelCollection($this->testRepository->findBy(['state' => TestState::AWAITING]));
    }

    /**
     * @param Test[] $tests
     */
    private function cancelCollection(array $tests): void
    {
        foreach ($tests as $test) {
            $this->testStateMutator->setCancelled($test);
        }
    }
}
