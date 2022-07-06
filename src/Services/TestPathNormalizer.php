<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\String\UnicodeString;

class TestPathNormalizer
{
    public function __construct(
        private readonly string $compilerSourceDirectory,
        private readonly string $compilerTargetDirectory,
    ) {
    }

    public function removeCompilerSourcePrefix(string $path): string
    {
        $path = trim($path);

        return $this->trimPrefix($path, $this->compilerSourceDirectory);
    }

    public function removeCompilerTargetPrefix(string $path): string
    {
        $path = trim($path);

        return $this->trimPrefix($path, $this->compilerTargetDirectory);
    }

    private function trimPrefix(string $path, string $prefix): string
    {
        return (string) (new UnicodeString($path))->trimPrefix($prefix . '/');
    }
}
