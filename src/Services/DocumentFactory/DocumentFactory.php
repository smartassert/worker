<?php

declare(strict_types=1);

namespace App\Services\DocumentFactory;

use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidTestException;
use App\Model\Document\Document;
use App\Model\Document\Test;
use App\Services\TestPathNormalizer;

class DocumentFactory
{
    public function __construct(
        private readonly TestPathNormalizer $testPathNormalizer,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     * @throws InvalidTestException
     */
    public function createTest(array $data): Test
    {
        $document = new Document($data);
        $type = $document->getType();

        if ('test' === $type) {
            $path = $this->testPathNormalizer->normalize(
                (string) $document->getPayloadStringValue('path')
            );

            if ('' === $path) {
                throw new InvalidTestException(
                    $data,
                    'Payload path missing',
                    InvalidTestException::CODE_PATH_MISSING
                );
            }

            $payload = $document->getPayload();
            $payload['path'] = $path;
            $data['payload'] = $payload;

            return new Test($path, $data);
        }

        throw new InvalidDocumentException(
            $data,
            sprintf('Type "%s" is not "test"', $type),
            InvalidDocumentException::CODE_TYPE_INVALID
        );
    }
}
