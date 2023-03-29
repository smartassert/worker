<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\DocumentFactory;

use App\Enum\ExecutionExceptionScope;
use App\Model\Document\Exception;
use App\Model\Document\StepException;
use App\Services\DocumentFactory\DocumentFactoryInterface;
use App\Services\DocumentFactory\ExceptionFactory;

class ExceptionFactoryTest extends AbstractDocumentFactoryTestCase
{
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
     * @return array<mixed>
     */
    public function createDataProvider(): array
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
                'expected' => new StepException(
                    'step name',
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
