<?php

declare(strict_types=1);

namespace App\Model;

use SmartAssert\YamlFile\Collection\ProviderInterface;
use SmartAssert\YamlFile\Exception\ProvisionException;
use SmartAssert\YamlFile\YamlFile;

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

    /**
     * @throws ProvisionException
     *
     * @return \Generator<YamlFile>
     */
    public function getSources(): \Generator
    {
        return $this->yamlFiles->getYamlFiles();
    }
}
