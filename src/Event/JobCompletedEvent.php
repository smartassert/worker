<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class JobCompletedEvent extends Event implements EventInterface
{
    public function getPayload(): array
    {
        return [];
    }

    public function getReferenceComponents(): array
    {
        return [];
    }
}
