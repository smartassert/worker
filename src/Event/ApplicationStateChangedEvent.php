<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\ApplicationState;
use Symfony\Contracts\EventDispatcher\Event;

class ApplicationStateChangedEvent extends Event
{
    public function __construct(
        public readonly ApplicationState $previousState,
        public readonly ApplicationState $newState,
    ) {}
}
