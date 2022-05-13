<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\WorkerEventType;
use Symfony\Contracts\EventDispatcher\Event;

class JobReadyEvent extends Event implements EventInterface
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
