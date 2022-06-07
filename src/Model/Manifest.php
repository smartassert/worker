<?php

declare(strict_types=1);

namespace App\Model;

class Manifest
{
    /**
     * @param array<int, non-empty-string> $testPaths
     */
    public function __construct(
        public readonly array $testPaths,
    ) {
    }

    public function isTestPath(string $path): bool
    {
        return in_array($path, $this->testPaths);
    }
}
