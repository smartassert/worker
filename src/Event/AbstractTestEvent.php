<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Test;
use Symfony\Contracts\EventDispatcher\Event;
use webignition\YamlDocument\Document;

abstract class AbstractTestEvent extends Event implements TestEventInterface
{
    public function __construct(
        private readonly Test $test,
        private readonly Document $document,
        private readonly string $path
    ) {
    }

    public function getTest(): Test
    {
        return $this->test;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
