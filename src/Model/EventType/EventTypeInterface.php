<?php

declare(strict_types=1);

namespace App\Model\EventType;

interface EventTypeInterface
{
    /**
     * @return non-empty-string
     */
    public function serialize(): string;
}
