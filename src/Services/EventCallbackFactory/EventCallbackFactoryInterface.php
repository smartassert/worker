<?php

declare(strict_types=1);

namespace App\Services\EventCallbackFactory;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use Symfony\Contracts\EventDispatcher\Event;

interface EventCallbackFactoryInterface
{
    public function handles(Event $event): bool;

    public function createForEvent(Job $job, Event $event): ?WorkerEvent;
}
