<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\YamlDocumentFactory;
use PHPUnit\Framework\TestCase;
use webignition\YamlDocument\Document;

class YamlDocumentFactoryTest extends TestCase
{
    private YamlDocumentFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new YamlDocumentFactory();
    }

    /**
     * @dataProvider processDataProvider
     *
     * @param string[]   $contentChunks
     * @param Document[] $expectedDocuments
     */
    public function testProcess(array $contentChunks, array $expectedDocuments): void
    {
        $documents = [];
        $onDocumentCreated = function (Document $document) use (&$documents) {
            $documents[] = $document;
        };

        $this->factory->start();
        $this->factory->setOnDocumentCreated($onDocumentCreated);

        foreach ($contentChunks as $chunk) {
            $this->factory->process($chunk);
        }

        $this->factory->stop();

        self::assertEquals($expectedDocuments, $documents);
    }

    /**
     * @return array<mixed>
     */
    public function processDataProvider(): array
    {
        return [
            'empty' => [
                'contentChunks' => [],
                'expectedDocuments' => [],
            ],
            'single document, no start delimiter, no end delimiter' => [
                'contentChunks' => [
                    '- item1',
                    '- item2',
                    '- item3',
                ],
                'expectedDocuments' => [
                    new Document(
                        '- item1' . "\n" .
                        '- item2' . "\n" .
                        '- item3' . "\n"
                    ),
                ],
            ],
            'single document, has start delimiter, no end delimiter' => [
                'contentChunks' => [
                    '---',
                    '- item1',
                    '- item2',
                    '- item3',
                ],
                'expectedDocuments' => [
                    new Document(
                        '- item1' . "\n" .
                        '- item2' . "\n" .
                        '- item3' . "\n"
                    ),
                ],
            ],
            'single document, no start delimiter, has end delimiter' => [
                'contentChunks' => [
                    '- item1',
                    '- item2',
                    '- item3',
                    '...',
                ],
                'expectedDocuments' => [
                    new Document(
                        '- item1' . "\n" .
                        '- item2' . "\n" .
                        '- item3' . "\n"
                    ),
                ],
            ],
            'single document, has start delimiter, has end delimiter' => [
                'contentChunks' => [
                    '---',
                    '- item1',
                    '- item2',
                    '- item3',
                    '...',
                ],
                'expectedDocuments' => [
                    new Document(
                        '- item1' . "\n" .
                        '- item2' . "\n" .
                        '- item3' . "\n"
                    ),
                ],
            ],
            'two documents' => [
                'contentChunks' => [
                    '---',
                    '- item1.1',
                    '- item1.2',
                    '...',
                    '---',
                    '- item2.1',
                    '- item2.2',
                    '...',
                ],
                'expectedDocuments' => [
                    new Document(
                        '- item1.1' . "\n" .
                        '- item1.2' . "\n"
                    ),
                    new Document(
                        '- item2.1' . "\n" .
                        '- item2.2' . "\n"
                    ),
                ],
            ],
            'two documents, no final end delimiter' => [
                'contentChunks' => [
                    '---',
                    '- item1.1',
                    '- item1.2',
                    '...',
                    '---',
                    '- item2.1',
                    '- item2.2',
                ],
                'expectedDocuments' => [
                    new Document(
                        '- item1.1' . "\n" .
                        '- item1.2' . "\n"
                    ),
                    new Document(
                        '- item2.1' . "\n" .
                        '- item2.2' . "\n"
                    ),
                ],
            ],
            'two documents, more than one line per chunk' => [
                'contentChunks' => [
                    '---' . "\n" . '- item1.1',
                    '- item1.2',
                    '- item1.3' . "\n" . '...' . "\n" . '---',
                    '- item2.1' . "\n" . '- item2.2',
                    '- item2.3',
                    '...',
                ],
                'expectedDocuments' => [
                    new Document(
                        '- item1.1' . "\n" .
                        '- item1.2' . "\n" .
                        '- item1.3' . "\n"
                    ),
                    new Document(
                        '- item2.1' . "\n" .
                        '- item2.2' . "\n" .
                        '- item2.3' . "\n"
                    ),
                ],
            ],
            'newline at end of multi-link chunks is ignored' => [
                'contentChunks' => [
                    "---\n",
                    "- item1\n- item2\n",
                    "- item3\n",
                    '...',
                ],
                'expectedDocuments' => [
                    new Document(
                        '- item1' . "\n" .
                        '- item2' . "\n" .
                        '- item3' . "\n"
                    ),
                ],
            ],
        ];
    }
}
