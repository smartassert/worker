<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\WorkerEventType;

interface EventInterface
{
    /**
     * @return array<mixed>
     */
    public function getPayload(): array;

    /**
     * @return string[]
     */
    public function getReferenceComponents(): array;

    public function getType(): WorkerEventType;
}
