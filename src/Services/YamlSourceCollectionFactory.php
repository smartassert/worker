<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\InvalidManifestException;
use App\Exception\MissingManifestException;
use App\Model\Manifest;
use App\Model\YamlSourceCollection;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\ProviderInterface;
use SmartAssert\YamlFile\YamlFile;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class YamlSourceCollectionFactory
{
    private const MANIFEST_FILENAME = 'manifest.yaml';

    public function __construct(
        private Parser $yamlParser,
    ) {
    }

    /**
     * @throws MissingManifestException
     * @throws InvalidManifestException
     */
    public function create(ProviderInterface $provider): YamlSourceCollection
    {
        $sources = [];
        $manifest = null;

        /** @var YamlFile $yamlFile */
        foreach ($provider->getYamlFiles() as $yamlFile) {
            if (self::MANIFEST_FILENAME === (string) $yamlFile->name) {
                $manifest = $this->createManifest($yamlFile);
            } else {
                $sources[] = $yamlFile;
            }
        }

        if (null === $manifest) {
            throw new MissingManifestException();
        }

        return new YamlSourceCollection($manifest, new ArrayCollection($sources));
    }

    /**
     * @throws InvalidManifestException
     */
    private function createManifest(YamlFile $yamlFile): Manifest
    {
        if ('' === trim($yamlFile->content)) {
            throw InvalidManifestException::createForEmptyContent($yamlFile->content);
        }

        try {
            $data = $this->yamlParser->parse($yamlFile->content);
        } catch (ParseException $parseException) {
            throw InvalidManifestException::createForInvalidYaml($yamlFile->content, $parseException);
        }

        if (false === is_array($data)) {
            throw InvalidManifestException::createForInvalidData($yamlFile->content);
        }

        foreach ($data as $value) {
            if (false === is_string($value)) {
                throw InvalidManifestException::createForInvalidData($yamlFile->content);
            }
        }

        return new Manifest($data);
    }
}
