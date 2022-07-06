<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\DocumentFactory;

use App\Exception\Document\InvalidTestException;
use App\Model\Document\Test;
use App\Services\DocumentFactory\DocumentFactoryInterface;
use App\Services\DocumentFactory\TestFactory;
use App\Services\TestPathNormalizer;

class TestFactoryTest extends AbstractDocumentFactoryTest
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';
    private const COMPILER_TARGET_DIRECTORY = '/app/target';

    /**
     * @return array<mixed>
     */
    public function createInvalidTypeDataProvider(): array
    {
        return [
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
     * @dataProvider createInvalidTestDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateInvalidTest(array $data, InvalidTestException $expected): void
    {
        self::expectExceptionObject($expected);

        $this->factory->create($data);
    }

    /**
     * @return array<mixed>
     */
    public function createInvalidTestDataProvider(): array
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
     * @return array<mixed>
     */
    public function createDataProvider(): array
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

    protected function createFactory(): DocumentFactoryInterface
    {
        return new TestFactory(
            new TestPathNormalizer(
                self::COMPILER_SOURCE_DIRECTORY,
                self::COMPILER_TARGET_DIRECTORY,
            )
        );
    }
}
