<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\DocumentFactory;

use App\Enum\ExecutionExceptionScope;
use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\Exception;
use App\Services\DocumentFactory\DocumentFactoryInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractDocumentFactoryTest extends TestCase
{
    protected DocumentFactoryInterface $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFactory();
    }

    /**
     * @dataProvider createEmptyTypeDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateEmptyType(array $data): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('Type empty');

        $this->factory->create($data);
    }

    /**
     * @return array<mixed>
     */
    public function createEmptyTypeDataProvider(): array
    {
        return [
            'no data' => [
                'data' => [],
            ],
            'type not present' => [
                'data' => ['key1' => 'value1', 'key2' => 'value2'],
            ],
            'type is empty' => [
                'data' => ['type' => ''],
            ],
            'type is whitespace-only' => [
                'data' => ['type' => '  '],
            ],
        ];
    }

    abstract protected function createFactory(): DocumentFactoryInterface;

//    /**
//     * @dataProvider createProvider
//     *
//     * @param array<mixed> $data
//     */
//    public function testCreate(array $data, Exception $expected): void
//    {
//        self::assertEquals($expected, $this->factory->create($data));
//    }
//
//    /**
//     * @return array<mixed>
//     */
//    public function createProvider(): array
//    {
//        return [
//            'test-scope exception' => [
//                'data' => [
//                    'type' => 'exception',
//                    'payload' => [
//                        'step' => null,
//                        'class' => self::class,
//                        'message' => 'test-scope exception message',
//                        'code' => 123,
//                    ],
//                ],
//                'expected' => new Exception(
//                    ExecutionExceptionScope::TEST,
//                    [
//                        'type' => 'exception',
//                        'payload' => [
//                            'step' => null,
//                            'class' => self::class,
//                            'message' => 'test-scope exception message',
//                            'code' => 123,
//                        ],
//                    ]
//                ),
//            ],
//            'step-scope exception' => [
//                'data' => [
//                    'type' => 'exception',
//                    'payload' => [
//                        'step' => 'step name',
//                        'class' => self::class,
//                        'message' => 'step-scope exception message',
//                        'code' => 456,
//                    ],
//                ],
//                'expected' => new Exception(
//                    ExecutionExceptionScope::STEP,
//                    [
//                        'type' => 'exception',
//                        'payload' => [
//                            'step' => 'step name',
//                            'class' => self::class,
//                            'message' => 'step-scope exception message',
//                            'code' => 456,
//                        ],
//                    ]
//                ),
//            ],
//        ];
//    }
}
