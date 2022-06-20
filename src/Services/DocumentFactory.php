<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\DocumentInterface;
use App\Model\Document\Step;
use App\Model\Document\Test;

class DocumentFactory
{
    private const TYPE_TEST = 'test';
    private const TYPE_STEP = 'step';
    private const VALID_TYPES = [self::TYPE_TEST, self::TYPE_STEP];

    public function __construct(
        private readonly TestPathMutator $testPathMutator,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     */
    public function create(array $data): DocumentInterface
    {
        $type = $data['type'] ?? null;
        $type = is_string($type) ? trim($type) : null;

        if ('step' === $type) {
            return new Step($data);
        }

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
            sprintf(
                'Type "%s" is not one of "%s"',
                (string) $type,
                implode(', ', self::VALID_TYPES)
            ),
            InvalidDocumentException::CODE_TYPE_INVALID
        );
    }
}
