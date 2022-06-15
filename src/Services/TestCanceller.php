<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Enum\TestState;
use App\Event\JobTimeoutEvent;
use App\Event\StepFailedEvent;
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
            StepFailedEvent::class => [
                ['cancelAwaitingFromTestFailedEvent', 0],
            ],
            JobTimeoutEvent::class => [
                ['cancelUnfinished', 0],
            ],
        ];
    }

    public function cancelAwaitingFromTestFailedEvent(StepFailedEvent $event): void
    {
        $this->cancelAwaiting();
    }

    public function cancelUnfinished(): void
    {
        $this->cancelCollection($this->testRepository->findAllUnfinished());
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
