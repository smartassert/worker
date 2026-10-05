<?php

declare(strict_types=1);

namespace App\Event\EventDelivery;

use App\Entity\WorkerEvent;
use Symfony\Contracts\EventDispatcher\Event;

class SendingEvent extends Event
{
    public function __construct(
        public readonly WorkerEvent $workerEvent,
    ) {}
}
