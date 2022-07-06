<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\DocumentFactory;

use App\Enum\ExecutionExceptionScope;
use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\Exception;
use App\Services\DocumentFactory\DocumentFactoryInterface;
use App\Services\DocumentFactory\ExceptionFactory;

class ExceptionFactoryTest extends AbstractDocumentFactoryTest
{
    /**
     * @dataProvider createInvalidTypeDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateInvalidType(array $data, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->factory->create($data);
    }

    /**
     * @return array<mixed>
     */
    public function createInvalidTypeDataProvider(): array
    {
        return [
            'type is not exception: test' => [
                'data' => ['type' => 'test'],
                'expectedExceptionMessage' => 'Type "test" is not "exception"',
            ],
            'type is not exception: step' => [
                'data' => ['type' => 'step'],
                'expectedExceptionMessage' => 'Type "step" is not "exception"',
            ],
            'type is not step: invalid' => [
                'data' => ['type' => 'invalid'],
                'expectedExceptionMessage' => 'Type "invalid" is not "exception"',
            ],
        ];
    }

    /**
     * @dataProvider createProvider
     *
     * @param array<mixed> $data
     */
    public function testCreate(array $data, Exception $expected): void
    {
        self::assertEquals($expected, $this->factory->create($data));
    }

    /**
     * @return array<mixed>
     */
    public function createProvider(): array
    {
        return [
            'test-scope exception' => [
                'data' => [
                    'type' => 'exception',
                    'payload' => [
                        'step' => null,
                        'class' => self::class,
                        'message' => 'test-scope exception message',
                        'code' => 123,
                    ],
                ],
                'expected' => new Exception(
                    ExecutionExceptionScope::TEST,
                    [
                        'type' => 'exception',
                        'payload' => [
                            'step' => null,
                            'class' => self::class,
                            'message' => 'test-scope exception message',
                            'code' => 123,
                        ],
                    ]
                ),
            ],
            'step-scope exception' => [
                'data' => [
                    'type' => 'exception',
                    'payload' => [
                        'step' => 'step name',
                        'class' => self::class,
                        'message' => 'step-scope exception message',
                        'code' => 456,
                    ],
                ],
                'expected' => new Exception(
                    ExecutionExceptionScope::STEP,
                    [
                        'type' => 'exception',
                        'payload' => [
                            'step' => 'step name',
                            'class' => self::class,
                            'message' => 'step-scope exception message',
                            'code' => 456,
                        ],
                    ]
                ),
            ],
        ];
    }

    protected function createFactory(): DocumentFactoryInterface
    {
        return new ExceptionFactory();
    }
}
