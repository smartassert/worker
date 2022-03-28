<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\InvalidManifestException;
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
            $payload = [
                'message' => 'Serialized source metadata cannot be decoded',
                'file_hashes_content' => $previous->getEncodedContent(),
            ];

            if ($previous->getPrevious() instanceof \Throwable) {
                $payload['previous_message'] = $previous->getPrevious()->getMessage();
            }

            return new ErrorResponse('source/metadata/invalid', $payload);
        }

        if ($previous instanceof FilePathNotFoundException) {
            return new ErrorResponse('source/metadata/incomplete', [
                'message' => 'Serialized source metadata is not complete',
                'hash' => $previous->getHash(),
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

        $payload = ['message' => $exception->getMessage()];
        if ($exception->getPrevious() instanceof \Throwable) {
            $payload['previous_message'] = $exception->getPrevious()->getMessage();
        }

        return new ErrorResponse('source/manifest/' . $manifestState, $payload);
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
