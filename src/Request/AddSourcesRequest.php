<?php

declare(strict_types=1);

namespace App\Request;

use App\Model\Manifest;
use App\Model\UploadedFileKey;
use App\Model\UploadedSource;
use App\Model\UploadedSourceCollection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use webignition\EncapsulatingRequestResolverBundle\Model\EncapsulatingRequestInterface;

class AddSourcesRequest implements EncapsulatingRequestInterface
{
    public const KEY_MANIFEST = 'manifest';

    public function __construct(
        private ?Manifest $manifest,
        private UploadedSourceCollection $uploadedSources
    ) {
    }

    public static function create(Request $request): AddSourcesRequest
    {
        $files = $request->files;

        $manifestKey = new UploadedFileKey(self::KEY_MANIFEST);
        $encodedManifestKey = $manifestKey->encode();

        $manifestFile = $files->get($encodedManifestKey);
        $manifest = $manifestFile instanceof UploadedFile ? new Manifest($manifestFile) : null;

        $files->remove($encodedManifestKey);

        $uploadedSources = [];
        foreach ($files as $encodedKey => $file) {
            $key = UploadedFileKey::fromEncodedKey($encodedKey);
            $path = (string) $key;

            if ($file instanceof UploadedFile) {
                $uploadedSources[$path] = new UploadedSource($path, $file);
            }
        }

        $uploadedSources = new UploadedSourceCollection($uploadedSources);

        return new AddSourcesRequest($manifest, $uploadedSources);
    }

    public function getManifest(): ?Manifest
    {
        return $this->manifest;
    }

    public function getUploadedSources(): UploadedSourceCollection
    {
        return $this->uploadedSources;
    }
}
