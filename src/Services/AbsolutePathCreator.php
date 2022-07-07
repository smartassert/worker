<?php

declare(strict_types=1);

namespace App\Services;

class AbsolutePathCreator
{
    /**
     * @param non-empty-string $prefix
     */
    public function __construct(
        private readonly string $prefix,
    ) {
    }

    /**
     * @param non-empty-string $relativePath
     *
     * @return non-empty-string
     */
    public function create(string $relativePath): string
    {
        return $this->prefix . '/' . ltrim($relativePath, '/');
    }
}
