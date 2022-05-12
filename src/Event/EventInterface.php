<?php

declare(strict_types=1);

namespace App\Event;

interface EventInterface
{
    /**
     * @return array<mixed>
     */
    public function getPayload(): array;
}
