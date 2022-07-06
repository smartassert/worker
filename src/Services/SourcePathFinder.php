<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Repository\TestRepository;

class SourcePathFinder
{
    public function __construct(
        private TestRepository $testRepository,
        private SourceRepository $sourceRepository,
        private readonly TestPathNormalizer $testPathNormalizer,
    ) {
    }

    /**
     * @return null|non-empty-string
     */
    public function findNextNonCompiledPath(): ?string
    {
        $sourcePaths = $this->sourceRepository->findAllPaths(Source::TYPE_TEST);
        $testPaths = $this->testRepository->findAllSources();
        $testPaths = $this->removeCompilerSourceDirectoryPrefixFromPaths($testPaths);

        foreach ($sourcePaths as $sourcePath) {
            if (!in_array($sourcePath, $testPaths)) {
                return $sourcePath;
            }
        }

        return null;
    }

    /**
     * @param string[] $paths
     *
     * @return string[]
     */
    private function removeCompilerSourceDirectoryPrefixFromPaths(array $paths): array
    {
        $strippedPaths = [];

        foreach ($paths as $path) {
            if (is_string($path)) {
                $strippedPaths[] = $this->testPathNormalizer->removeCompilerSourcePrefix($path);
            }
        }

        return $strippedPaths;
    }
}
