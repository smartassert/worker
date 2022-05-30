<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\WorkerEventType;
use Symfony\Contracts\EventDispatcher\Event;

class JobStartedEvent extends Event implements EventInterface
{
    /**
     * @param string[] $testPaths
     */
    public function __construct(
        private readonly array $testPaths,
    ) {
    }

    public function getPayload(): array
    {
        return [
            'tests' => $this->testPaths,
        ];
    }

    public function getReferenceComponents(): array
    {
        return [];
    }

    public function getType(): WorkerEventType
    {
        return WorkerEventType::JOB_STARTED;
    }
}
