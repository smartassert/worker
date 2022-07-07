<?php

declare(strict_types=1);

namespace App\Services;

use webignition\BasilCompilerModels\Factory\TestManifestFactory as BaseTestManifestFactory;
use webignition\BasilCompilerModels\Factory\TestManifestFactoryInterface;
use webignition\BasilCompilerModels\Model\TestManifest;

class TestManifestFactory implements TestManifestFactoryInterface
{
    /**
     * @param non-empty-string $compilerSourceDirectory
     */
    public function __construct(
        private readonly BaseTestManifestFactory $baseTestManifestFactory,
        private readonly string $compilerSourceDirectory,
    ) {
    }

    public function create(array $data): TestManifest
    {
        $source = $data['source'] ?? null;
        if (is_string($source)) {
            $data['source'] = $this->makeSourcePathRelative($source);
        }

        return $this->baseTestManifestFactory->create($data);
    }

    private function makeSourcePathRelative(string $path): string
    {
        if (!str_starts_with($path, $this->compilerSourceDirectory)) {
            return $path;
        }

        $prefixLength = strlen($this->compilerSourceDirectory);
        $pathWithoutPrefix = substr($path, $prefixLength);

        if (str_starts_with($pathWithoutPrefix, '/')) {
            $pathWithoutPrefix = ltrim($pathWithoutPrefix, '/');
        }

        return $pathWithoutPrefix;
    }
}
