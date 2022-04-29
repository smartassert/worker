<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Services\ExecutionState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class ExecutionStateTest extends AbstractBaseFunctionalTest
{
    private ExecutionState $executionState;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $executionState = self::getContainer()->get(ExecutionState::class);
        if ($executionState instanceof ExecutionState) {
            $this->executionState = $executionState;
        }

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

        self::assertSame($expectedState, (string) $this->executionState);
    }

    /**
     * @return array<mixed>
     */
    public function getDataProvider(): array
    {
        return [
            'awaiting: not has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup(),
                'expectedState' => ExecutionState::STATE_AWAITING,
            ],
            'running: not has finished tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(Test::STATE_RUNNING),
                    ]),
                'expectedState' => ExecutionState::STATE_RUNNING,
            ],
            'awaiting: not has finished tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(Test::STATE_AWAITING),
                    ]),
                'expectedState' => ExecutionState::STATE_AWAITING,
            ],
            'running: has complete tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withState(Test::STATE_RUNNING),
                    ]),
                'expectedState' => ExecutionState::STATE_RUNNING,
            ],
            'running: has complete tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withState(Test::STATE_AWAITING),
                    ]),
                'expectedState' => ExecutionState::STATE_RUNNING,
            ],
            'complete: has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_COMPLETE),
                    ]),
                'expectedState' => ExecutionState::STATE_COMPLETE,
            ],
            'cancelled: has failed tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_FAILED),
                    ]),
                'expectedState' => ExecutionState::STATE_CANCELLED,
                'expectedIsNotStates' => [
                    ExecutionState::STATE_AWAITING,
                    ExecutionState::STATE_RUNNING,
                    ExecutionState::STATE_COMPLETE,
                ],
            ],
            'cancelled: has cancelled tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_CANCELLED),
                    ]),
                'expectedState' => ExecutionState::STATE_CANCELLED,
            ],
        ];
    }

    /**
     * @dataProvider isDataProvider
     *
     * @param array<ExecutionState::STATE_*> $expectedIsStates
     * @param array<ExecutionState::STATE_*> $expectedIsNotStates
     */
    public function testIs(
        EnvironmentSetup $setup,
        array $expectedIsStates,
        array $expectedIsNotStates
    ): void {
        $this->environmentFactory->create($setup);

        self::assertTrue($this->executionState->is(...$expectedIsStates));
        self::assertFalse($this->executionState->is(...$expectedIsNotStates));
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
                    ExecutionState::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::STATE_RUNNING,
                    ExecutionState::STATE_COMPLETE,
                    ExecutionState::STATE_CANCELLED,
                ],
            ],
            'running: not has finished tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(Test::STATE_RUNNING),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::STATE_AWAITING,
                    ExecutionState::STATE_COMPLETE,
                    ExecutionState::STATE_CANCELLED,
                ],
            ],
            'awaiting: not has finished tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(Test::STATE_AWAITING),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::STATE_AWAITING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::STATE_RUNNING,
                    ExecutionState::STATE_COMPLETE,
                    ExecutionState::STATE_CANCELLED,
                ],
            ],
            'running: has complete tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withState(Test::STATE_RUNNING),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::STATE_AWAITING,
                    ExecutionState::STATE_COMPLETE,
                    ExecutionState::STATE_CANCELLED,
                ],
            ],
            'running: has complete tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_COMPLETE),
                        (new TestSetup())->withState(Test::STATE_AWAITING),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::STATE_RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::STATE_AWAITING,
                    ExecutionState::STATE_COMPLETE,
                    ExecutionState::STATE_CANCELLED,
                ],
            ],
            'complete: has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_COMPLETE),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::STATE_COMPLETE,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::STATE_AWAITING,
                    ExecutionState::STATE_RUNNING,
                    ExecutionState::STATE_CANCELLED,
                ],
            ],
            'cancelled: has failed tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_FAILED),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::STATE_CANCELLED,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::STATE_AWAITING,
                    ExecutionState::STATE_RUNNING,
                    ExecutionState::STATE_COMPLETE,
                ],
            ],
            'cancelled: has cancelled tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(Test::STATE_CANCELLED),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::STATE_CANCELLED,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::STATE_AWAITING,
                    ExecutionState::STATE_RUNNING,
                    ExecutionState::STATE_COMPLETE,
                ],
            ],
        ];
    }
}
