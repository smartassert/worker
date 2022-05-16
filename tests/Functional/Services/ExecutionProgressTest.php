<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Enum\TestState;
use App\Services\ExecutionProgress;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class ExecutionProgressTest extends AbstractBaseFunctionalTest
{
    private ExecutionProgress $executionProgress;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $executionProgress = self::getContainer()->get(ExecutionProgress::class);
        \assert($executionProgress instanceof ExecutionProgress);
        $this->executionProgress = $executionProgress;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider getDataProvider
     */
    public function testGet(EnvironmentSetup $setup, string $expectedState): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedState, $this->executionProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'awaiting: not has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup(),
                'expectedState' => ExecutionProgress::STATE_AWAITING,
            ],
            'running: not has finished tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::RUNNING),
                    ]),
                'expectedState' => ExecutionProgress::STATE_RUNNING,
            ],
            'awaiting: not has finished tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING),
                    ]),
                'expectedState' => ExecutionProgress::STATE_AWAITING,
            ],
            'running: has complete tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::RUNNING),
                    ]),
                'expectedState' => ExecutionProgress::STATE_RUNNING,
            ],
            'running: has complete tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::AWAITING),
                    ]),
                'expectedState' => ExecutionProgress::STATE_RUNNING,
            ],
            'complete: has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                    ]),
                'expectedState' => ExecutionProgress::STATE_COMPLETE,
            ],
            'cancelled: has failed tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::FAILED),
                    ]),
                'expectedState' => ExecutionProgress::STATE_CANCELLED,
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_AWAITING,
                    ExecutionProgress::STATE_RUNNING,
                    ExecutionProgress::STATE_COMPLETE,
                ],
            ],
            'cancelled: has cancelled tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::CANCELLED),
                    ]),
                'expectedState' => ExecutionProgress::STATE_CANCELLED,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param array<ExecutionProgress::STATE_*> $expectedIsStates
     * @param array<ExecutionProgress::STATE_*> $expectedIsNotStates
     */
    public function testIs(
        EnvironmentSetup $setup,
        array $expectedIsStates,
        array $expectedIsNotStates
    ): void {
        $this->environmentFactory->create($setup);

        self::assertTrue($this->executionProgress->is(...$expectedIsStates));
        self::assertFalse($this->executionProgress->is(...$expectedIsNotStates));
    }

    /**
     * @return array<mixed>
     */
    public function isDataProvider(): array
    {
        return [
            'awaiting: not has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup(),
                'expectedIsStates' => [
                    ExecutionProgress::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_RUNNING,
                    ExecutionProgress::STATE_COMPLETE,
                    ExecutionProgress::STATE_CANCELLED,
                ],
            ],
            'running: not has finished tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::RUNNING),
                    ]),
                'expectedIsStates' => [
                    ExecutionProgress::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_AWAITING,
                    ExecutionProgress::STATE_COMPLETE,
                    ExecutionProgress::STATE_CANCELLED,
                ],
            ],
            'awaiting: not has finished tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING),
                    ]),
                'expectedIsStates' => [
                    ExecutionProgress::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_RUNNING,
                    ExecutionProgress::STATE_COMPLETE,
                    ExecutionProgress::STATE_CANCELLED,
                ],
            ],
            'running: has complete tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::RUNNING),
                    ]),
                'expectedIsStates' => [
                    ExecutionProgress::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_AWAITING,
                    ExecutionProgress::STATE_COMPLETE,
                    ExecutionProgress::STATE_CANCELLED,
                ],
            ],
            'running: has complete tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::AWAITING),
                    ]),
                'expectedIsStates' => [
                    ExecutionProgress::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_AWAITING,
                    ExecutionProgress::STATE_COMPLETE,
                    ExecutionProgress::STATE_CANCELLED,
                ],
            ],
            'complete: has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                    ]),
                'expectedIsStates' => [
                    ExecutionProgress::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_AWAITING,
                    ExecutionProgress::STATE_RUNNING,
                    ExecutionProgress::STATE_CANCELLED,
                ],
            ],
            'cancelled: has failed tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::FAILED),
                    ]),
                'expectedIsStates' => [
                    ExecutionProgress::STATE_CANCELLED,
                ],
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_AWAITING,
                    ExecutionProgress::STATE_RUNNING,
                    ExecutionProgress::STATE_COMPLETE,
                ],
            ],
            'cancelled: has cancelled tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::CANCELLED),
                    ]),
                'expectedIsStates' => [
                    ExecutionProgress::STATE_CANCELLED,
                ],
                'expectedIsNotStates' => [
                    ExecutionProgress::STATE_AWAITING,
                    ExecutionProgress::STATE_RUNNING,
                    ExecutionProgress::STATE_COMPLETE,
                ],
            ],
        ];
    }
}
