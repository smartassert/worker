<?php

declare(strict_types=1);

namespace App\Event;

use Psr\EventDispatcher\StoppableEventInterface;

interface SourceEventInterface extends StoppableEventInterface
{
    public function getSource(): string;
}
