<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\InvalidManifestException;
use App\Exception\MissingTestSourceException;
use App\Response\ErrorResponse;
use SmartAssert\YamlFile\Exception\Collection\DeserializeException;
use SmartAssert\YamlFile\Exception\Collection\FilePathNotFoundException;
use SmartAssert\YamlFile\Exception\FileHashesDeserializer\ExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorResponseFactory
{
    public function createFromYamlFileCollectionDeserializeException(DeserializeException $exception): ?JsonResponse
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof ExceptionInterface) {
            return $this->createForException($previous, 'source/metadata/invalid', [
                'message' => 'Serialized source metadata cannot be decoded',
                'file_hashes_content' => $previous->getEncodedContent(),
            ]);
        }

        if ($previous instanceof FilePathNotFoundException) {
            return $this->createForException($previous, 'source/metadata/incomplete', [
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

        return $this->createForException($exception, 'source/manifest/' . $manifestState);
    }

    public function createFromMissingTestSourceException(MissingTestSourceException $exception): JsonResponse
    {
        return $this->createForException($exception, 'source/test/missing', [
            'message' => sprintf('Test source "%s" missing', $exception->getPath()),
            'path' => $exception->getPath()
        ]);
    }

    /**
     * @param array<mixed> $additionalPayload
     */
    private function createForException(
        \Throwable $exception,
        string $errorState,
        array $additionalPayload = []
    ): ErrorResponse {
        $payload = ['message' => $exception->getMessage()];
        $previous = $exception->getPrevious();
        if ($previous instanceof \Throwable) {
            $payload['previous_message'] = $previous->getMessage();
        }

        return new ErrorResponse($errorState, array_merge($payload, $additionalPayload));
    }
}
