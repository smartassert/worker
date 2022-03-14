<?php

declare(strict_types=1);

namespace App\Model;

class Manifest
{
    /**
     * @param array<string> $testPaths
     */
    public function __construct(
        private array $testPaths,
    ) {
    }

    /**
     * @return string[]
     */
    public function getTestPaths(): array
    {
        return $this->testPaths;
    }

    public function isTestPath(string $path): bool
    {
        return in_array($path, $this->getTestPaths());
    }
}
