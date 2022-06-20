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
     * @dataProvider invalidTypeDataProvider
     *
     * @param array<mixed> $data
     */
    public function testInvalidType(array $data, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->factory->create($data);
    }

    /**
     * @return array<mixed>
     */
    public function invalidTypeDataProvider(): array
    {
        return [
            'no data' => [
                'data' => [],
                'expectedExceptionMessage' => 'Type "" is not one of "test, step"',
            ],
            'type not present' => [
                'data' => ['key1' => 'value1', 'key2' => 'value2'],
                'expectedExceptionMessage' => 'Type "" is not one of "test, step"',
            ],
            'type is empty' => [
                'data' => ['type' => ''],
                'expectedExceptionMessage' => 'Type "" is not one of "test, step"',
            ],
            'type is whitespace-only' => [
                'data' => ['type' => '  '],
                'expectedExceptionMessage' => 'Type "" is not one of "test, step"',
            ],
            'type is not one of test, step' => [
                'data' => ['type' => 'invalid'],
                'expectedExceptionMessage' => 'Type "invalid" is not one of "test, step"',
            ],
        ];
    }

    /**
     * @dataProvider createDataProvider
     *
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     */
    public function testCreateFoo(array $data, DocumentInterface $expected): void
    {
        $document = $this->factory->create($data);

        self::assertInstanceOf(DocumentInterface::class, $document);
        self::assertEquals($expected, $document);
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $transformedPath = 'Test/test.yml';
        $transformablePath = self::COMPILER_SOURCE_DIRECTORY . '/' . $transformedPath;

        return [
            'step' => [
                'data' => [
                    'type' => 'step',
                ],
                'expected' => new Step([
                    'type' => 'step',
                ]),
            ],
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
}
