<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Enum\ExecutionState;
use App\Enum\TestState;
use App\Services\ExecutionProgress;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExecutionProgressTest extends WebTestCase
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

    #[DataProvider('getDataProvider')]
    public function testGet(EnvironmentSetup $setup, ExecutionState $expectedState): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedState, $this->executionProgress->get());
    }

    /**
     * @return array<mixed>
     */
    public static function getDataProvider(): array
    {
        return [
            'awaiting: not has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup(),
                'expectedState' => ExecutionState::AWAITING,
            ],
            'running: not has finished tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::RUNNING),
                    ]),
                'expectedState' => ExecutionState::RUNNING,
            ],
            'awaiting: not has finished tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING),
                    ]),
                'expectedState' => ExecutionState::AWAITING,
            ],
            'running: has complete tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::RUNNING),
                    ]),
                'expectedState' => ExecutionState::RUNNING,
            ],
            'running: has complete tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::AWAITING),
                    ]),
                'expectedState' => ExecutionState::RUNNING,
            ],
            'complete: has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                    ]),
                'expectedState' => ExecutionState::COMPLETE,
            ],
            'cancelled: has failed tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::FAILED),
                    ]),
                'expectedState' => ExecutionState::CANCELLED,
            ],
            'cancelled: has cancelled tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::CANCELLED),
                    ]),
                'expectedState' => ExecutionState::CANCELLED,
            ],
        ];
    }

    /**
     * @param ExecutionState[] $expectedIsStates
     * @param ExecutionState[] $expectedIsNotStates
     */
    #[DataProvider('isDataProvider')]
    public function testIs(EnvironmentSetup $setup, array $expectedIsStates, array $expectedIsNotStates): void
    {
        $this->environmentFactory->create($setup);

        self::assertContains($this->executionProgress->get(), $expectedIsStates);
        self::assertNotContains($this->executionProgress->get(), $expectedIsNotStates);
    }

    /**
     * @return array<mixed>
     */
    public static function isDataProvider(): array
    {
        return [
            'awaiting: not has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => new EnvironmentSetup(),
                'expectedIsStates' => [
                    ExecutionState::AWAITING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::RUNNING,
                    ExecutionState::COMPLETE,
                    ExecutionState::CANCELLED,
                ],
            ],
            'running: not has finished tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::RUNNING),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::AWAITING,
                    ExecutionState::COMPLETE,
                    ExecutionState::CANCELLED,
                ],
            ],
            'awaiting: not has finished tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::AWAITING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::RUNNING,
                    ExecutionState::COMPLETE,
                    ExecutionState::CANCELLED,
                ],
            ],
            'running: has complete tests and has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::RUNNING),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::AWAITING,
                    ExecutionState::COMPLETE,
                    ExecutionState::CANCELLED,
                ],
            ],
            'running: has complete tests and not has running tests and has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::AWAITING),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::RUNNING,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::AWAITING,
                    ExecutionState::COMPLETE,
                    ExecutionState::CANCELLED,
                ],
            ],
            'complete: has finished tests and not has running tests and not has awaiting tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::COMPLETE),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::COMPLETE,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::AWAITING,
                    ExecutionState::RUNNING,
                    ExecutionState::CANCELLED,
                ],
            ],
            'cancelled: has failed tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::FAILED),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::CANCELLED,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::AWAITING,
                    ExecutionState::RUNNING,
                    ExecutionState::COMPLETE,
                ],
            ],
            'cancelled: has cancelled tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::CANCELLED),
                    ]),
                'expectedIsStates' => [
                    ExecutionState::CANCELLED,
                ],
                'expectedIsNotStates' => [
                    ExecutionState::AWAITING,
                    ExecutionState::RUNNING,
                    ExecutionState::COMPLETE,
                ],
            ],
        ];
    }
}
