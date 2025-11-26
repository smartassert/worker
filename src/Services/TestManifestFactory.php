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
     * @param non-empty-string $compilerTargetDirectory
     */
    public function __construct(
        private readonly BaseTestManifestFactory $baseTestManifestFactory,
        private readonly string $compilerSourceDirectory,
        private readonly string $compilerTargetDirectory,
    ) {}

    public function create(array $data): TestManifest
    {
        $source = $data['source'] ?? null;
        if (is_string($source)) {
            $data['source'] = $this->removePathPrefix($source, $this->compilerSourceDirectory);
        }

        $target = $data['target'] ?? null;
        if (is_string($target)) {
            $data['target'] = $this->removePathPrefix($target, $this->compilerTargetDirectory);
        }

        return $this->baseTestManifestFactory->create($data);
    }

    private function removePathPrefix(string $path, string $prefix): string
    {
        if (!str_starts_with($path, $prefix)) {
            return $path;
        }

        $prefixLength = strlen($prefix);
        $pathWithoutPrefix = substr($path, $prefixLength);

        if (str_starts_with($pathWithoutPrefix, '/')) {
            $pathWithoutPrefix = ltrim($pathWithoutPrefix, '/');
        }

        return $pathWithoutPrefix;
    }
}
