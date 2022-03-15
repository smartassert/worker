<?php

declare(strict_types=1);

namespace App\Model;

use SmartAssert\YamlFile\Provider\ProviderInterface;

class SerializedSource
{
    public function __construct(
        private readonly ProviderInterface $yamlFileProvider,
    ) {
    }

    public function getSources(): ProviderInterface
    {
        return $this->yamlFileProvider;
    }
}
