<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\Document\InvalidDocumentException;
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
     */
    public function createTest(array $data): Test
    {
        $document = new Document($data);
        $type = $document->getType();

        if ('test' === $type) {
            $test = new Test($document->getData());

            $path = $test->getPath();
            $mutatedPath = $this->testPathMutator->removeCompilerSourceDirectoryFromPath($test->getPath());

            if ($mutatedPath !== $path) {
                $test->setPath($mutatedPath);
            }

            return $test;
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
     */
    public function createStep(array $data): Step
    {
        $document = new Document($data);
        $type = $document->getType();

        if ('step' === $type) {
            return new Step($document->getData());
        }

        throw new InvalidDocumentException(
            $document->getData(),
            sprintf('Type "%s" is not "step"', $type),
            InvalidDocumentException::CODE_TYPE_INVALID
        );
    }
}
