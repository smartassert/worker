<?php

declare(strict_types=1);

namespace App\Services;

class TargetPathCreator
{
    /**
     * @param non-empty-string $compilerTargetDirectory
     */
    public function __construct(
        private readonly string $compilerTargetDirectory,
    ) {
    }

    /**
     * @param non-empty-string $relativePath
     *
     * @return non-empty-string
     */
    public function createAbsolutePath(string $relativePath): string
    {
        return $this->compilerTargetDirectory . '/' . ltrim($relativePath, '/');
    }
}
