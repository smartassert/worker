<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\Test as TestEntity;
use App\Entity\TestConfiguration;
use App\Model\Document\Test as TestDocument;
use App\Services\TestDocumentFactory;
use App\Services\TestPathMutator;
use PHPUnit\Framework\TestCase;
use webignition\YamlDocument\Document;
use webignition\YamlDocumentGenerator\YamlGenerator;

class TestDocumentFactoryTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';

    private TestDocumentFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new TestDocumentFactory(
            new YamlGenerator(),
            new TestPathMutator(self::COMPILER_SOURCE_DIRECTORY)
        );
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(TestEntity $test, TestDocument $expected): void
    {
        self::assertEquals($expected, $this->factory->create($test));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $transformedPath = 'Test/test.yml';
        $transformablePath = self::COMPILER_SOURCE_DIRECTORY . '/' . $transformedPath;

        return [
            'test with already-transformed path' => [
                'test' => $this->createTestEntity($transformedPath),
                'expectedTest' => $this->createTestDocument($transformedPath),
            ],
            'test with transformable path' => [
                'test' => $this->createTestEntity($transformablePath),
                'expectedTest' => $this->createTestDocument($transformedPath),
            ],
            'test with non-transformable absolute path' => [
                'test' => $this->createTestEntity('/app/Test/non-transformable.yml'),
                'expectedTest' => $this->createTestDocument('/app/Test/non-transformable.yml'),
            ],
        ];
    }

    private function createTestEntity(string $path): TestEntity
    {
        return new TestEntity(
            new TestConfiguration('chrome', 'http://example.com'),
            $path,
            '/app/target/GeneratedTest.php',
            ['step 1', 'step 2'],
            1
        );
    }

    private function createTestDocument(string $path): TestDocument
    {
        return new TestDocument(
            new Document((string) json_encode([
                'type' => 'test',
                'payload' => [
                    'path' => $path,
                    'config' => [
                        'browser' => 'chrome',
                        'url' => 'http://example.com',
                    ],
                ],
            ]))
        );
    }
}
