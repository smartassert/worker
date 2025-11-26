<?php

declare(strict_types=1);

namespace App\Tests\Services;

use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\ProviderInterface;
use SmartAssert\YamlFile\FileHashes;
use SmartAssert\YamlFile\YamlFile;

class YamlProviderFactory
{
    public function __construct(
        private readonly FixtureReader $fixtureReader,
    ) {}

    /**
     * @param string[] $sourcePaths
     */
    public function create(array $sourcePaths): ProviderInterface
    {
        $yamlFiles = [];

        $fileHashes = new FileHashes();
        foreach ($sourcePaths as $sourcePath) {
            $content = trim($this->fixtureReader->read($sourcePath));

            $yamlFiles[] = YamlFile::create($sourcePath, $content);
            $fileHashes->add($sourcePath, md5($content));
        }

        return new ArrayCollection($yamlFiles);
    }
}
