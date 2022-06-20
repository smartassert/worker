<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\DocumentInterface;
use App\Model\Document\Step;
use App\Model\Document\Test;
use App\Services\DocumentFactory;
use App\Services\TestPathMutator;
use PHPUnit\Framework\TestCase;

class DocumentFactoryTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';

    private DocumentFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new DocumentFactory(
            new TestPathMutator(self::COMPILER_SOURCE_DIRECTORY)
        );
    }

    /**
     * @dataProvider createStepInvalidTypeDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateStepInvalidType(array $data, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->factory->createStep($data);
    }

    /**
     * @return array<mixed>
     */
    public function createStepInvalidTypeDataProvider(): array
    {
        return [
            'no data' => [
                'data' => [],
                'expectedExceptionMessage' => 'Type empty',
            ],
            'type not present' => [
                'data' => ['key1' => 'value1', 'key2' => 'value2'],
                'expectedExceptionMessage' => 'Type empty',
            ],
            'type is empty' => [
                'data' => ['type' => ''],
                'expectedExceptionMessage' => 'Type empty',
            ],
            'type is whitespace-only' => [
                'data' => ['type' => '  '],
                'expectedExceptionMessage' => 'Type empty',
            ],
            'type is not step: test' => [
                'data' => ['type' => 'test'],
                'expectedExceptionMessage' => 'Type "test" is not "step"',
            ],
            'type is not step: invalid' => [
                'data' => ['type' => 'invalid'],
                'expectedExceptionMessage' => 'Type "invalid" is not "step"',
            ],
        ];
    }

    /**
     * @dataProvider createTestInvalidTypeDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateTestInvalidType(array $data, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->factory->createTest($data);
    }

    /**
     * @return array<mixed>
     */
    public function createTestInvalidTypeDataProvider(): array
    {
        return [
            'no data' => [
                'data' => [],
                'expectedExceptionMessage' => 'Type empty',
            ],
            'type not present' => [
                'data' => ['key1' => 'value1', 'key2' => 'value2'],
                'expectedExceptionMessage' => 'Type empty',
            ],
            'type is empty' => [
                'data' => ['type' => ''],
                'expectedExceptionMessage' => 'Type empty',
            ],
            'type is not test: step' => [
                'data' => ['type' => 'step'],
                'expectedExceptionMessage' => 'Type "step" is not "test"',
            ],
            'type is not test: invalid' => [
                'data' => ['type' => 'invalid'],
                'expectedExceptionMessage' => 'Type "invalid" is not "test"',
            ],
        ];
    }

    /**
     * @dataProvider createTestDataProvider
     *
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     */
    public function testCreateTest(array $data, DocumentInterface $expected): void
    {
        $document = $this->factory->createTest($data);

        self::assertInstanceOf(DocumentInterface::class, $document);
        self::assertEquals($expected, $document);
    }

    /**
     * @return array<mixed>
     */
    public function createTestDataProvider(): array
    {
        $transformedPath = 'Test/test.yml';
        $transformablePath = self::COMPILER_SOURCE_DIRECTORY . '/' . $transformedPath;

        return [
            'test with no path' => [
                'data' => [
                    'type' => 'test',
                ],
                'expected' => new Test([
                    'type' => 'test',
                ]),
            ],
            'test with already transformed path' => [
                'data' => [
                    'type' => 'test',
                    'path' => $transformedPath,
                ],
                'expected' => new Test([
                    'type' => 'test',
                    'path' => $transformedPath,
                ]),
            ],
            'test with transformable path' => [
                'data' => [
                    'type' => 'test',
                    'payload' => [
                        'path' => $transformablePath,
                    ],
                ],
                'expected' => new Test([
                    'type' => 'test',
                    'payload' => [
                        'path' => $transformedPath,
                    ],
                ]),
            ],
            'test with non-transformable absolute path' => [
                'data' => [
                    'type' => 'test',
                    'payload' => [
                        'path' => '/app/Test/non-transformable.yml',
                    ],
                ],
                'expected' => new Test([
                    'type' => 'test',
                    'payload' => [
                        'path' => '/app/Test/non-transformable.yml',
                    ],
                ]),
            ],
        ];
    }

    /**
     * @dataProvider createStepDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateStep(array $data, Step $expected): void
    {
        self::assertEquals($expected, $this->factory->createStep($data));
    }

    /**
     * @return array<mixed>
     */
    public function createStepDataProvider(): array
    {
        return [
            'step' => [
                'data' => [
                    'type' => 'step',
                ],
                'expected' => new Step([
                    'type' => 'step',
                ]),
            ],
        ];
    }
}
