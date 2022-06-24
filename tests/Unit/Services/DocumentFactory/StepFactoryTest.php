<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\DocumentFactory;

use App\Exception\Document\InvalidDocumentException;
use App\Exception\Document\InvalidStepException;
use App\Model\Document\Step;
use App\Services\DocumentFactory\StepFactory;
use PHPUnit\Framework\TestCase;

class StepFactoryTest extends TestCase
{
    private StepFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new StepFactory();
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

        $this->factory->create($data);
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
     * @dataProvider createStepInvalidStepDataProvider
     *
     * @param array<mixed> $data
     */
    public function testCreateStepInvalidStep(array $data, InvalidStepException $expected): void
    {
        self::expectExceptionObject($expected);

        $this->factory->create($data);
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
        self::assertEquals($expected, $this->factory->create($data));
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
