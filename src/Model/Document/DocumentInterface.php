<?php

declare(strict_types=1);

namespace App\Model\Document;

interface DocumentInterface
{
    public function getType(): string;

    /**
     * @return array<mixed>
     */
    public function getData(): array;
}
