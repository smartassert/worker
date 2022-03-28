<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\InvalidManifestException;
use App\Exception\MissingManifestException;
use App\Exception\MissingTestSourceException;
use App\Response\ErrorResponse;
use SmartAssert\YamlFile\Exception\Collection\DeserializeException;
use SmartAssert\YamlFile\Exception\Collection\FilePathNotFoundException;
use SmartAssert\YamlFile\Exception\FileHashesDeserializer\ExceptionInterface;
use SmartAssert\YamlFile\Exception\ProvisionException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorResponseFactory
{
    public function createFromYamlFileCollectionDeserializeException(DeserializeException $exception): ?JsonResponse
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof ExceptionInterface) {
            return new ErrorResponse('source/metadata/invalid', [
                'message' => 'Serialized source metadata cannot be decoded',
                'file_hashes_content' => $previous->getEncodedContent(),
                'previous_message' => $previous->getPrevious()?->getMessage(),
            ]);
        }

        if ($previous instanceof FilePathNotFoundException) {
            return new ErrorResponse('source/metadata/incomplete', [
                'message' => 'Serialized source metadata is not complete',
                'hash' => $previous->getHash(),
                'previous_message' => $previous->getPrevious()?->getMessage(),
            ]);
        }

        return null;
    }

    public function createFromInvalidManifestException(InvalidManifestException $exception): JsonResponse
    {
        $manifestState = 'unknown';
        if (InvalidManifestException::CODE_EMPTY === $exception->getCode()) {
            $manifestState = 'empty';
        }

        if (InvalidManifestException::CODE_INVALID_YAML === $exception->getCode()) {
            $manifestState = 'invalid';
        }

        return new ErrorResponse('source/manifest/' . $manifestState, [
            'message' => $exception->getMessage(),
            'previous_message' => $exception->getPrevious()?->getMessage(),
        ]);
    }

    public function createFromMissingManifestException(MissingManifestException $exception): JsonResponse
    {
        return new ErrorResponse('source/manifest/missing');
    }

    public function createFromProvisionException(ProvisionException $exception): JsonResponse
    {
        return new ErrorResponse('invalid_manifest', [
            'message' => $exception->getMessage(),
            'previous_message' => $exception->getPrevious()?->getMessage(),
        ]);
    }

    public function createFromMissingTestSourceException(MissingTestSourceException $exception): JsonResponse
    {
        return new ErrorResponse('source/test/missing', [
            'message' => sprintf('Test source "%s" missing', $exception->getPath()),
            'path' => $exception->getPath()
        ]);
    }
}
