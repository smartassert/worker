<?php

declare(strict_types=1);

namespace App\Services;

class SourcePathCreator
{
    /**
     * @param non-empty-string $compilerSourceDirectory
     */
    public function __construct(
        private readonly string $compilerSourceDirectory,
    ) {
    }

    /**
     * @param non-empty-string $relativePath
     *
     * @return non-empty-string
     */
    public function createAbsolutePath(string $relativePath): string
    {
        return $this->compilerSourceDirectory . '/' . ltrim($relativePath, '/');
    }
}
