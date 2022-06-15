<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Repository\TestRepository;
use Symfony\Component\String\UnicodeString;

class SourcePathFinder
{
    public function __construct(
        private TestRepository $testRepository,
        private SourceRepository $sourceRepository,
        private string $compilerSourceDirectory,
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
                $strippedPaths[] = (string) (new UnicodeString($path))->trimPrefix(
                    $this->compilerSourceDirectory . '/'
                );
            }
        }

        return $strippedPaths;
    }
}
