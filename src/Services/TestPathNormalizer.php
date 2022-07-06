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

    public function normalize(string $path): string
    {
        $path = trim($path);

        $normalizedPath = $this->trimPrefix($path, $this->compilerSourceDirectory);
        if ($path !== $normalizedPath) {
            return $normalizedPath;
        }

        return $this->trimPrefix($path, $this->compilerTargetDirectory);
    }

    private function trimPrefix(string $path, string $prefix): string
    {
        return (string) (new UnicodeString($path))->trimPrefix($prefix . '/');
    }
}
