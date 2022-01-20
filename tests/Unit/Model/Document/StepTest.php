<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Document;

use App\Model\Document\Step;
use PHPUnit\Framework\TestCase;
use webignition\YamlDocument\Document;

class StepTest extends TestCase
{
    /**
     * @dataProvider isStepDataProvider
     */
    public function testIsStep(Step $step, bool $expectedIsStep): void
    {
        self::assertSame($expectedIsStep, $step->isStep());
    }

    /**
     * @return array<mixed>
     */
    public function isStepDataProvider(): array
    {
        return [
            'empty' => [
                'step' => new Step(
                    new Document()
                ),
                'expectedIsStep' => false,
            ],
            'no type' => [
                'step' => new Step(
                    new Document('key: value')
                ),
                'expectedIsStep' => false,
            ],
            'type is not step' => [
                'step' => new Step(
                    new Document('type: test')
                ),
                'expectedIsStep' => false,
            ],
            'is a step' => [
                'step' => new Step(
                    new Document('type: step')
                ),
                'expectedIsStep' => true,
            ],
        ];
    }

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
                'step' => new Step(
                    new Document()
                ),
                'expectedIsPassed' => false,
            ],
            'no status' => [
                'step' => new Step(
                    new Document('key: value')
                ),
                'expectedIsPassed' => false,
            ],
            'status is not passed' => [
                'step' => new Step(
                    new Document('status: failed')
                ),
                'expectedIsPassed' => false,
            ],
            'status is passed' => [
                'step' => new Step(
                    new Document('status: passed')
                ),
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
                'step' => new Step(
                    new Document()
                ),
                'expectedIsFailed' => false,
            ],
            'no status' => [
                'step' => new Step(
                    new Document('key: value')
                ),
                'expectedIsFailed' => false,
            ],
            'status is not failed' => [
                'step' => new Step(
                    new Document('status: passed')
                ),
                'expectedIsFailed' => false,
            ],
            'status is failed' => [
                'step' => new Step(
                    new Document('status: failed')
                ),
                'expectedIsFailed' => true,
            ],
        ];
    }
}
