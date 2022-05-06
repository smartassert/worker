<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Test as TestEntity;
use App\Model\Document\Test as TestDocument;
use App\Model\RunnerTest\TestProxy;
use webignition\YamlDocument\Document;
use webignition\YamlDocumentGenerator\YamlGenerator;

class TestDocumentFactory
{
    public function __construct(
        private readonly YamlGenerator $yamlGenerator,
        private readonly TestDocumentMutator $testDocumentMutator
    ) {
    }

    public function create(TestEntity $test): TestDocument
    {
        $runnerTest = new TestProxy($test);
        $runnerTestString = $this->yamlGenerator->generate($runnerTest->getData());

        $document = new Document($runnerTestString);
        $mutatedDocument = $this->testDocumentMutator->removeCompilerSourceDirectoryFromPath($document);

        return new TestDocument($mutatedDocument);
    }
}
