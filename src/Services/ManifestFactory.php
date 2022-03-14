<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\Manifest\InvalidMimeTypeException;
use App\Model\Manifest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Yaml\Parser as YamlParser;

class ManifestFactory
{
    public const MIME_TYPE = 'text/yaml';

    public function __construct(
        private YamlParser $yamlParser
    ) {
    }

    /**
     * @throws InvalidMimeTypeException
     */
    public function createFromUploadedFile(UploadedFile $uploadedFile): Manifest
    {
        $mimeType = $uploadedFile->getClientMimeType();
        if (self::MIME_TYPE !== $uploadedFile->getClientMimeType()) {
            throw new InvalidMimeTypeException($mimeType);
        }

        $data = $this->yamlParser->parse($uploadedFile->getContent());
        $data = is_array($data) ? $data : [];

        $testPaths = $data['tests'] ?? [];
        $testPaths = is_array($testPaths) ? $testPaths : [];
        $testPaths = array_filter($testPaths, function ($item) {
            return is_string($item) && '' !== $item;
        });

        return new Manifest($testPaths);
    }
}
