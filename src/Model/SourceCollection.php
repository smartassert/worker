<?php

declare(strict_types=1);

namespace App\Model;

use SmartAssert\YamlFile\Model\YamlFile;
use SmartAssert\YamlFile\Provider\ArrayProvider;
use SmartAssert\YamlFile\Provider\ProviderInterface;

class SourceCollection
{
    private ?YamlFile $manifestYamlFile;
    private ProviderInterface $sources;

    public function __construct(
        private readonly ProviderInterface $yamlFileProvider,
    ) {
    }

    public function initialize(): void
    {
        $sources = [];

        /** @var YamlFile $yamlFile */
        foreach ($this->yamlFileProvider->provide() as $yamlFile) {
            if ('manifest.yaml' === (string) $yamlFile->name) {
                $this->manifestYamlFile = $yamlFile;
            } else {
                $sources[] = $yamlFile;
            }
        }

        $this->sources = new ArrayProvider($sources);
    }

    public function getManifestYamlFile(): ?YamlFile
    {
        return $this->manifestYamlFile;
    }

    public function getSources(): ProviderInterface
    {
        return $this->sources;
    }
}
