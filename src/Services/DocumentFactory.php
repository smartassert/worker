<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Exception\Document\InvalidTestException;
use App\Model\Document\Document;
use App\Model\Document\Step;
use App\Model\Document\Test;

class DocumentFactory
{
    public function __construct(
        private readonly TestPathMutator $testPathMutator,
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
            $path = $document->getPayloadStringValue('path');

            if (null === $path) {
                throw new InvalidTestException(
                    $document->getData(),
                    'Payload path missing',
                    InvalidTestException::CODE_PATH_MISSING
                );
            }

            $mutatedPath = $this->testPathMutator->removeCompilerSourceDirectoryFromPath($path);
            if ($mutatedPath !== $path) {
                $payload = $document->getPayload();
                $payload['path'] = $mutatedPath;
                $data['payload'] = $payload;
            }

            return new Test($mutatedPath, $data);
        }

        throw new InvalidDocumentException(
            $document->getData(),
            sprintf('Type "%s" is not "test"', $type),
            InvalidDocumentException::CODE_TYPE_INVALID
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     * @throws InvalidStepException
     */
    public function createStep(array $data): Step
    {
        $document = new Document($data);
        $type = $document->getType();

        if ('step' === $type) {
            $name = $document->getPayloadStringValue('name');

            if (null === $name) {
                throw new InvalidStepException(
                    $document->getData(),
                    'Payload name missing',
                    InvalidStepException::CODE_NAME_MISSING
                );
            }

            return new Step($name, $document->getData());
        }

        throw new InvalidDocumentException(
            $document->getData(),
            sprintf('Type "%s" is not "step"', $type),
            InvalidDocumentException::CODE_TYPE_INVALID
        );
    }
}
