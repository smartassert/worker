<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test as TestEntity;
use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\Test as TestDocument;
use App\Model\RunnerTest\TestProxy;
use webignition\YamlDocument\Document;
use webignition\YamlDocumentGenerator\YamlGenerator;

class TestDocumentFactory
{
    public function __construct(
        private readonly YamlGenerator $yamlGenerator,
        private readonly TestPathMutator $testPathMutator
    ) {
    }

    /**
     * @throws InvalidDocumentException
     */
    public function create(TestEntity $test): TestDocument
    {
        $runnerTest = new TestProxy($test);
        $runnerTestString = $this->yamlGenerator->generate($runnerTest->getData());

        $document = new Document($runnerTestString);
        $documentData = $document->parse();
        $documentData = is_array($documentData) ? $documentData : [];

        $testDocument = new TestDocument($documentData);

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
