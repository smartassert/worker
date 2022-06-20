<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\Document\InvalidDocumentException;
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
        $type = $this->getType($data);

        if ('test' === $type) {
            $test = new Test($data);

            $path = $test->getPath();
            $mutatedPath = $this->testPathMutator->removeCompilerSourceDirectoryFromPath($test->getPath());

            if ($mutatedPath !== $path) {
                $test->setPath($mutatedPath);
            }

            return $test;
        }

        throw new InvalidDocumentException(
            $data,
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
        $type = $this->getType($data);

        if ('step' === $type) {
            return new Step($data);
        }

        throw new InvalidDocumentException(
            $data,
            sprintf('Type "%s" is not "step"', $type),
            InvalidDocumentException::CODE_TYPE_INVALID
        );
    }

    /**
     * @param array<mixed> $data
     */
    public function getType(array $data): ?string
    {
        $type = $data['type'] ?? null;

        return is_string($type) ? trim($type) : null;
    }
}
