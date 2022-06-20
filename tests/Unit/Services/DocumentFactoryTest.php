<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Exception\Document\InvalidTestException;
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
     * @dataProvider createTestInvalidTestDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateTestInvalidTest(array $data, InvalidTestException $expected): void
    {
        self::expectExceptionObject($expected);

        $this->factory->createTest($data);
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
        $document = $this->factory->createTest($data);

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

    /**
     * @dataProvider createStepInvalidStepDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateStepInvalidStep(array $data, InvalidStepException $expected): void
    {
        self::expectExceptionObject($expected);

        $this->factory->createStep($data);
    }

    /**
     * @return array<mixed>
     */
    public function createStepInvalidStepDataProvider(): array
    {
        return [
            'name missing' => [
                'data' => [
                    'type' => 'step',
                    'payload' => [],
                ],
                'expected' => new InvalidStepException(
                    [
                        'type' => 'step',
                        'payload' => [],
                    ],
                    'Payload name missing',
                    InvalidStepException::CODE_NAME_MISSING
                )
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
                    'payload' => [
                        'name' => 'step name',
                    ],
                ],
                'expected' => new Step(
                    'step name',
                    [
                        'type' => 'step',
                        'payload' => [
                            'name' => 'step name',
                        ],
                    ]
                ),
            ],
        ];
    }
}
