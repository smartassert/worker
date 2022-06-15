<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test;
use App\Enum\TestState;
use App\Enum\WorkerEventType;
use App\Event\StepEvent;
use App\Repository\TestRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestStateMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly TestRepository $testRepository,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StepEvent::class => [
                ['setFailedFromStepFailedEvent', 50],
            ],
        ];
    }

    public function setFailedFromStepFailedEvent(StepEvent $event): void
    {
        if (WorkerEventType::STEP_FAILED === $event->getType()) {
            $this->setFailed($event->getTest());
        }
    }

    public function setRunning(Test $test): void
    {
        $this->set($test, TestState::RUNNING);
    }

    public function setCompleteIfRunning(Test $test): void
    {
        if ($test->hasState(TestState::RUNNING)) {
            $this->set($test, TestState::COMPLETE);
        }
    }

    public function setFailed(Test $test): void
    {
        $this->set($test, TestState::FAILED);
    }

    public function setCancelled(Test $test): void
    {
        $this->set($test, TestState::CANCELLED);
    }

    private function set(Test $test, TestState $state): void
    {
        $test->setState($state);
        $this->testRepository->add($test);
    }
}
