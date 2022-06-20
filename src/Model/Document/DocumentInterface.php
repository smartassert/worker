<?php

declare(strict_types=1);

namespace App\Model\Document;

interface DocumentInterface
{
    /**
     * @return array<mixed>
     */
    public function getData(): array;
}
