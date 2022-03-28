<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\InvalidManifestException;
use App\Exception\MissingManifestException;
use App\Exception\MissingTestSourceException;
use SmartAssert\YamlFile\Exception\Collection\DeserializeException;
use SmartAssert\YamlFile\Exception\Collection\FilePathNotFoundException;
use SmartAssert\YamlFile\Exception\FileHashesDeserializer\ExceptionInterface;
use SmartAssert\YamlFile\Exception\ProvisionException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorResponseFactory
{
    /**
     * @param array<mixed> $payload
     */
    public function create(string $type, array $payload = [], int $statusCode = 400): JsonResponse
    {
        return new JsonResponse(
            [
                'error_state' => $type,
                'payload' => $payload,
            ],
            $statusCode
        );
    }

    public function createFromYamlFileCollectionDeserializeException(DeserializeException $exception): ?JsonResponse
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof ExceptionInterface) {
            return $this->create('invalid_serialized_source_metadata', [
                'message' => 'Serialized source metadata cannot be decoded',
                'file_hashes_content' => $previous->getEncodedContent(),
                'previous_message' => $previous->getPrevious()?->getMessage(),
            ]);
        }

        if ($previous instanceof FilePathNotFoundException) {
            return $this->create('incomplete_serialized_source_metadata', [
                'message' => 'Serialized source metadata is not complete',
                'hash' => $previous->getHash(),
                'previous_message' => $previous->getPrevious()?->getMessage(),
            ]);
        }

        return null;
    }

    public function createFromInvalidManifestException(InvalidManifestException $exception): JsonResponse
    {
        return $this->create('invalid_manifest', [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'previous_message' => $exception->getPrevious()?->getMessage(),
        ]);
    }

    public function createFromMissingManifestException(MissingManifestException $exception): JsonResponse
    {
        return $this->create('missing_manifest');
    }

    public function createFromProvisionException(ProvisionException $exception): JsonResponse
    {
        return $this->create('invalid_manifest', [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'previous_message' => $exception->getPrevious()?->getMessage(),
        ]);
    }

    public function createFromMissingTestSourceException(MissingTestSourceException $exception): JsonResponse
    {
        return $this->create('missing_test_source', [
            'message' => sprintf('Test source "%s" missing', $exception->getPath()),
            'path' => $exception->getPath()
        ]);
    }
}
