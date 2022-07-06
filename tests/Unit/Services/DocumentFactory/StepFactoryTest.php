<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\DocumentFactory;

use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Model\Document\Step;
use App\Services\DocumentFactory\DocumentFactoryInterface;
use App\Services\DocumentFactory\StepFactory;

class StepFactoryTest extends AbstractDocumentFactoryTest
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
            'type is not step: test' => [
                'data' => ['type' => 'test'],
                'expectedExceptionMessage' => 'Type "test" is not "step"',
            ],
            'type is not step: exception' => [
                'data' => ['type' => 'exception'],
                'expectedExceptionMessage' => 'Type "exception" is not "step"',
            ],
            'type is not step: invalid' => [
                'data' => ['type' => 'invalid'],
                'expectedExceptionMessage' => 'Type "invalid" is not "step"',
            ],
        ];
    }

    /**
     * @dataProvider createInvalidStepDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateInvalidStep(array $data, InvalidStepException $expected): void
    {
        self::expectExceptionObject($expected);

        $this->factory->create($data);
    }

    /**
     * @return array<mixed>
     */
    public function createInvalidStepDataProvider(): array
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
     * @return array<mixed>
     */
    public function createDataProvider(): array
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

    protected function createFactory(): DocumentFactoryInterface
    {
        return new StepFactory();
    }
}
