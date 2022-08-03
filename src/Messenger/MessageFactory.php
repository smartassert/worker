<?php

declare(strict_types=1);

namespace App\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class MessageFactory
{
    /**
     * @param array<class-string, int> $classNameToDelayMap
     */
    public function __construct(
        private readonly array $classNameToDelayMap,
    ) {
    }

    public function createDelayedEnvelope(object $message): Envelope
    {
        $delay = $this->classNameToDelayMap[$message::class] ?? null;

        return new Envelope($message, is_int($delay) ? [new DelayStamp($delay)] : []);
    }
}
