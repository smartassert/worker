<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\TestDocumentMutator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Dumper;
use webignition\YamlDocument\Document;

class TestDocumentMutatorTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';

    private TestDocumentMutator $mutator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mutator = new TestDocumentMutator(new Dumper(), self::COMPILER_SOURCE_DIRECTORY);
    }

    /**
     * @dataProvider removeCompilerSourceDirectoryFromPathDataProvider
     */
    public function testRemoveCompilerSourceDirectoryFromPath(Document $document, Document $expectedDocument): void
    {
        $mutatedDocument = $this->mutator->removeCompilerSourceDirectoryFromPath($document);

        self::assertEquals($expectedDocument, $mutatedDocument);
    }

    /**
     * @return Document[][]
     */
    public function removeCompilerSourceDirectoryFromPathDataProvider(): array
    {
        $step = new Document('{ type: step }');
        $testWithoutPrefixedPath = new Document('{ type: test, path: /path/to/test.yml }');
        $testWithPrefixedPath = new Document(
            '{ type: test, payload: { path: ' . self::COMPILER_SOURCE_DIRECTORY . '/Test/test.yml } }'
        );

        return [
            'document is step' => [
                'document' => $step,
                'expectedDocument' => $step,
            ],
            'test without prefixed path' => [
                'document' => $testWithoutPrefixedPath,
                'expectedDocument' => $testWithoutPrefixedPath,
            ],
            'test with prefixed path' => [
                'document' => $testWithPrefixedPath,
                'expectedDocument' => new Document('{ type: test, payload: { path: Test/test.yml } }'),
            ],
        ];
    }
}
