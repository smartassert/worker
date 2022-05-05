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
            'no payload' => [
                'step' => new Step(
                    new Document('key: value')
                ),
                'expectedIsPassed' => false,
            ],
            'no status' => [
                'step' => new Step(
                    new Document('payload: {}')
                ),
                'expectedIsPassed' => false,
            ],
            'status is not passed' => [
                'step' => new Step(
                    new Document('payload: { status: failed }')
                ),
                'expectedIsPassed' => false,
            ],
            'status is passed' => [
                'step' => new Step(
                    new Document('payload: { status: passed }')
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
            'no payload' => [
                'step' => new Step(
                    new Document('key: value')
                ),
                'expectedIsFailed' => false,
            ],
            'no status' => [
                'step' => new Step(
                    new Document('payload: {}')
                ),
                'expectedIsFailed' => false,
            ],
            'status is not failed' => [
                'step' => new Step(
                    new Document('payload: { status: failed }')
                ),
                'expectedIsFailed' => true,
            ],
            'status is failed' => [
                'step' => new Step(
                    new Document('payload: { status: passed }')
                ),
                'expectedIsFailed' => false,
            ],
        ];
    }

    /**
     * @dataProvider getNameDataProvider
     */
    public function testGetName(Step $step, ?string $expectedName): void
    {
        self::assertSame($expectedName, $step->getName());
    }

    /**
     * @return array<mixed>
     */
    public function getNameDataProvider(): array
    {
        return [
            'empty' => [
                'step' => new Step(
                    new Document()
                ),
                'expectedName' => null,
            ],
            'document has no type' => [
                'step' => new Step(
                    new Document('key: value')
                ),
                'expectedName' => null,
            ],
            'not a step' => [
                'step' => new Step(
                    new Document('type: test')
                ),
                'expectedName' => null,
            ],
            'no name' => [
                'step' => new Step(
                    new Document('type: step')
                ),
                'expectedName' => null,
            ],
            'null name' => [
                'step' => new Step(
                    new Document('type: step' . "\n" . 'payload: { name: ~ }')
                ),
                'expectedName' => null,
            ],
            'empty name' => [
                'step' => new Step(
                    new Document('type: step' . "\n" . 'payload: { name: "" }')
                ),
                'expectedName' => '',
            ],
            'non-empty name' => [
                'step' => new Step(
                    new Document('type: step' . "\n" . 'payload: { name: "non-empty name" }')
                ),
                'expectedName' => 'non-empty name',
            ],
        ];
    }
}
