<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\DocumentFactory;

use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidTestException;
use App\Model\Document\Test;
use App\Services\DocumentFactory\TestFactory;
use App\Services\TestPathNormalizer;
use PHPUnit\Framework\TestCase;

class TestFactoryTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';

    private TestFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new TestFactory(
            new TestPathNormalizer(self::COMPILER_SOURCE_DIRECTORY)
        );
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

        $this->factory->create($data);
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
            'type is not test: exception' => [
                'data' => ['type' => 'exception'],
                'expectedExceptionMessage' => 'Type "exception" is not "test"',
            ],
            'type is not test: invalid' => [
                'data' => ['type' => 'invalid'],
                'expectedExceptionMessage' => 'Type "invalid" is not "test"',
            ],
        ];
    }

    /**
     * @dataProvider createTestInvalidTestDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateTestInvalidTest(array $data, InvalidTestException $expected): void
    {
        self::expectExceptionObject($expected);

        $this->factory->create($data);
    }

    /**
     * @return array<mixed>
     */
    public function createTestInvalidTestDataProvider(): array
    {
        return [
            'path missing' => [
                'data' => [
                    'type' => 'test',
                    'payload' => [],
                ],
                'expected' => new InvalidTestException(
                    [
                        'type' => 'test',
                        'payload' => [],
                    ],
                    'Payload path missing',
                    InvalidTestException::CODE_PATH_MISSING
                )
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
    public function testCreateTest(array $data, Test $expected): void
    {
        $document = $this->factory->create($data);

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
            'test with already transformed path' => [
                'data' => [
                    'type' => 'test',
                    'payload' => [
                        'path' => $transformedPath,
                    ],
                ],
                'expected' => new Test(
                    $transformedPath,
                    [
                        'type' => 'test',
                        'payload' => [
                            'path' => $transformedPath,
                        ],
                    ]
                ),
            ],
            'test with transformable path' => [
                'data' => [
                    'type' => 'test',
                    'payload' => [
                        'path' => $transformablePath,
                    ],
                ],
                'expected' => new Test(
                    $transformedPath,
                    [
                        'type' => 'test',
                        'payload' => [
                            'path' => $transformedPath,
                        ],
                    ]
                ),
            ],
            'test with non-transformable absolute path' => [
                'data' => [
                    'type' => 'test',
                    'payload' => [
                        'path' => '/app/Test/non-transformable.yml',
                    ],
                ],
                'expected' => new Test(
                    '/app/Test/non-transformable.yml',
                    [
                        'type' => 'test',
                        'payload' => [
                            'path' => '/app/Test/non-transformable.yml',
                        ],
                    ]
                ),
            ],
        ];
    }
}
