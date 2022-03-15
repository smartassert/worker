<?php

declare(strict_types=1);

namespace App\Model;

use SmartAssert\YamlFile\Collection\ProviderInterface;

class YamlSourceCollection
{
    public function __construct(
        private readonly Manifest $manifest,
        private readonly ProviderInterface $yamlFiles,
    ) {
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function getYamlFiles(): ProviderInterface
    {
        return $this->yamlFiles;
    }
}
