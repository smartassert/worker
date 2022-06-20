<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Document;

use App\Model\Document\Step;
use PHPUnit\Framework\TestCase;

class StepTest extends TestCase
{
    /**
     * @dataProvider statusIsPassedDataProvider
     */
    public function testStatusIsPassed(Step $step, bool $expectedIsPassed): void
    {
        self::assertSame($expectedIsPassed, $step->statusIsPassed());
    }

    /**
     * @return array<mixed>
     */
    public function statusIsPassedDataProvider(): array
    {
        return [
            'empty' => [
                'step' => new Step('step name', []),
                'expectedIsPassed' => false,
            ],
            'no payload' => [
                'step' => new Step('step name', ['key' => 'value']),
                'expectedIsPassed' => false,
            ],
            'no status' => [
                'step' => new Step('step name', ['payload' => []]),
                'expectedIsPassed' => false,
            ],
            'status is not passed' => [
                'step' => new Step('step name', ['payload' => ['status' => 'failed']]),
                'expectedIsPassed' => false,
            ],
            'status is passed' => [
                'step' => new Step('step name', ['payload' => ['status' => 'passed']]),
                'expectedIsPassed' => true,
            ],
        ];
    }

    /**
     * @dataProvider statusIsFailedDataProvider
     */
    public function testStatusIsFailed(Step $step, bool $expectedIsFailed): void
    {
        self::assertSame($expectedIsFailed, $step->statusIsFailed());
    }

    /**
     * @return array<mixed>
     */
    public function statusIsFailedDataProvider(): array
    {
        return [
            'empty' => [
                'step' => new Step('step name', []),
                'expectedIsFailed' => false,
            ],
            'no payload' => [
                'step' => new Step('step name', ['key' => 'value']),
                'expectedIsFailed' => false,
            ],
            'no status' => [
                'step' => new Step('step name', ['payload' => []]),
                'expectedIsFailed' => false,
            ],
            'status is not failed' => [
                'step' => new Step('step name', ['payload' => ['status' => 'passed']]),
                'expectedIsFailed' => false,
            ],
            'status is failed' => [
                'step' => new Step('step name', ['payload' => ['status' => 'failed']]),
                'expectedIsFailed' => true,
            ],
        ];
    }

    /**
     * @dataProvider getNameDataProvider
     */
    public function testGetName(Step $step, string $expectedName): void
    {
        self::assertSame($expectedName, $step->getName());
    }

    /**
     * @return array<mixed>
     */
    public function getNameDataProvider(): array
    {
        return [
            'empty name' => [
                'step' => new Step(
                    '',
                    [
                        'type' => 'step',
                        'payload' => [
                            'name' => ''
                        ]
                    ]
                ),
                'expectedName' => '',
            ],
            'non-empty name' => [
                'step' => new Step(
                    'non-empty name',
                    [
                        'type' => 'step',
                        'payload' => [
                            'name' => 'non-empty name'
                        ]
                    ]
                ),
                'expectedName' => 'non-empty name',
            ],
        ];
    }
}
