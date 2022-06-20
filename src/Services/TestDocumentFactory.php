<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test as TestEntity;
use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\Test as TestDocument;
use webignition\BasilRunnerDocuments\Test as RunnerTest;
use webignition\BasilRunnerDocuments\TestConfiguration as RunnerTestConfiguration;

class TestDocumentFactory
{
    public function __construct(
        private readonly TestPathMutator $testPathMutator
    ) {
    }

    /**
     * @throws InvalidDocumentException
     */
    public function create(TestEntity $testEntity): TestDocument
    {
        $runnerTest = new RunnerTest(
            (string) $testEntity->getSource(),
            new RunnerTestConfiguration($testEntity->getBrowser(), $testEntity->getUrl()),
        );

        $testDocument = new TestDocument($runnerTest->getData());

        if ($testDocument->isTest()) {
            $path = $testDocument->getPath();
            $mutatedPath = $this->testPathMutator->removeCompilerSourceDirectoryFromPath($testDocument->getPath());

            if ($mutatedPath !== $path) {
                $testDocument->setPath($mutatedPath);
            }
        }

        return $testDocument;
    }
}
