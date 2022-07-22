<?php

declare(strict_types=1);

namespace App\Model;

class ResourceReferenceSource
{
    /**
     * @param non-empty-string[] $components
     */
    public function __construct(
        public readonly string $label,
        public readonly array $components,
    ) {
    }
}
