<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Test;
use App\Enum\TestState;
use App\Repository\TestRepository;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class TestRepositoryTest extends AbstractEntityRepositoryTest
{
    private TestRepository $repository;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(TestRepository::class);
        \assert($repository instanceof TestRepository);
        $this->repository = $repository;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider findMaxPositionDataProvider
     */
    public function testFindMaxPosition(EnvironmentSetup $setup, int $expectedMaxPosition): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedMaxPosition, $this->repository->findMaxPosition());
    }

    /**
     * @return array<mixed>
     */
    public function findMaxPositionDataProvider(): array
    {
        return [
            'empty' => [
                'setup' => new EnvironmentSetup(),
                'expectedMaxPosition' => 0,
            ],
            'one test, position 1' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(1),
                    ]),
                'expectedMaxPosition' => 1,
            ],
            'one test, position 3' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(3),
                    ]),
                'expectedMaxPosition' => 3,
            ],
            'three tests, position 1, 2, 3' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(1),
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(2),
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(3),
                    ]),
                'expectedMaxPosition' => 3,
            ],
            'three tests, position 3, 2, 1' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(3),
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(2),
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(1),
                    ]),
                'expectedMaxPosition' => 3,
            ],
            'three tests, position 1, 3, 2' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(1),
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(3),
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(2),
                    ]),
                'expectedMaxPosition' => 3,
            ],
        ];
    }

    /**
     * @dataProvider findNextAwaitingIdIsNullDataProvider
     */
    public function testFindNextAwaitingIdIsNull(EnvironmentSetup $setup): void
    {
        $this->environmentFactory->create($setup);

        self::assertNull($this->repository->findNextAwaitingId());
    }

    /**
     * @return array<mixed>
     */
    public function findNextAwaitingIdIsNullDataProvider(): array
    {
        return [
            'empty' => [
                'setup' => new EnvironmentSetup(),
            ],
            'running, failed, complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::RUNNING),
                        (new TestSetup())->withState(TestState::FAILED),
                        (new TestSetup())->withState(TestState::COMPLETE),
                    ])
            ],
        ];
    }

    /**
     * @dataProvider findNextAwaitingIdNotNullDataProvider
     *
     * @param Test[] $tests
     */
    public function testFindNextAwaitingIdNotNull(array $tests, int $nextAwaitingIndex): void
    {
        foreach ($tests as $test) {
            $this->persistEntity($test);
        }

        $nextAwaitingId = $this->repository->findNextAwaitingId();

        $allTests = $this->repository->findAll();
        $expectedTest = $allTests[$nextAwaitingIndex];

        self::assertSame($nextAwaitingId, $expectedTest->getId());
    }

    /**
     * @return array<mixed>
     */
    public function findNextAwaitingIdNotNullDataProvider(): array
    {
        $tests = $this->createTestsWithStates([
            'awaiting1' => TestState::AWAITING,
            'awaiting2' => TestState::AWAITING,
            'running' => TestState::RUNNING,
            'failed' => TestState::FAILED,
            'complete' => TestState::COMPLETE,
        ]);

        return [
            'awaiting1' => [
                'tests' => [
                    $tests['awaiting1'],
                ],
                'expectedNextAwaitingIndex' => 0,
            ],
            'awaiting2' => [
                'tests' => [
                    $tests['awaiting2'],
                ],
                'expectedNextAwaitingIndex' => 0,
            ],
            'awaiting1, awaiting2' => [
                'tests' => [
                    $tests['awaiting1'],
                    $tests['awaiting2'],
                ],
                'expectedNextAwaitingIndex' => 0,
            ],
            'awaiting2, awaiting1' => [
                'tests' => [
                    $tests['awaiting2'],
                    $tests['awaiting1'],
                ],
                'expectedNextAwaitingIndex' => 1,
            ],
            'running, failed, awaiting1, complete' => [
                'tests' => [
                    $tests['running'],
                    $tests['failed'],
                    $tests['awaiting1'],
                    $tests['complete']
                ],
                'expectedNextAwaitingIndex' => 2,
            ],
        ];
    }

    /**
     * @dataProvider findUnfinishedCountDataProvider
     *
     * @param Test[] $tests
     */
    public function testFindUnfinishedCount(array $tests, int $expectedUnfinishedCount): void
    {
        foreach ($tests as $test) {
            $this->persistEntity($test);
        }

        self::assertSame($expectedUnfinishedCount, $this->repository->findUnfinishedCount());
    }

    /**
     * @return array<mixed>
     */
    public function findUnfinishedCountDataProvider(): array
    {
        $tests = $this->createTestsWithStates([
            'awaiting1' => TestState::AWAITING,
            'awaiting2' => TestState::AWAITING,
            'running' => TestState::RUNNING,
            'failed' => TestState::FAILED,
            'complete' => TestState::COMPLETE,
        ]);

        return [
            'empty' => [
                'tests' => [],
                'expectedUnfinishedCount' => 0,
            ],
            'awaiting1' => [
                'tests' => [
                    $tests['awaiting1'],
                ],
                'expectedUnfinishedCount' => 1,
            ],
            'awaiting1, awaiting2' => [
                'tests' => [
                    $tests['awaiting1'],
                    $tests['awaiting2'],
                ],
                'expectedUnfinishedCount' => 2,
            ],
            'awaiting1, running' => [
                'tests' => [
                    $tests['awaiting1'],
                    $tests['running'],
                ],
                'expectedUnfinishedCount' => 2,
            ],
            'all states' => [
                'tests' => [
                    $tests['awaiting1'],
                    $tests['awaiting2'],
                    $tests['running'],
                    $tests['failed'],
                    $tests['complete'],
                ],
                'expectedUnfinishedCount' => 3,
            ],
        ];
    }

    /**
     * @dataProvider findAllSourcesDataProvider
     *
     * @param string[] $expectedSources
     */
    public function testFindAllSources(EnvironmentSetup $setup, array $expectedSources): void
    {
        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);

        $environmentFactory->create($setup);

        self::assertSame($expectedSources, $this->repository->findAllSources());
    }

    /**
     * @return array<mixed>
     */
    public function findAllSourcesDataProvider(): array
    {
        return [
            'empty' => [
                'setup' => new EnvironmentSetup(),
                'expectedSources' => [],
            ],
            'single' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withSource('test1.yaml'),
                    ]),
                'expectedSources' => [
                    'test1.yaml',
                ],
            ],
            'multiple' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withSource('test1.yaml'),
                        (new TestSetup())->withSource('test2.yaml'),
                        (new TestSetup())->withSource('test3.yaml'),
                    ]),
                'expectedSources' => [
                    'test1.yaml',
                    'test2.yaml',
                    'test3.yaml',
                ],
            ],
            'multiple, is sorted' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withSource('test2.yaml'),
                        (new TestSetup())->withSource('test1.yaml'),
                        (new TestSetup())->withSource('test3.yaml'),
                    ]),
                'expectedSources' => [
                    'test1.yaml',
                    'test2.yaml',
                    'test3.yaml',
                ],
            ],
        ];
    }

    /**
     * @param TestState[] $states
     *
     * @return Test[]
     */
    private function createTestsWithStates(array $states): array
    {
        $tests = [];
        $position = 1;

        foreach ($states as $key => $state) {
            $tests[$key] = $this->createTestWithStateAndPosition($state, $position);
            ++$position;
        }

        return $tests;
    }

    private function createTestWithStateAndPosition(TestState $state, int $position): Test
    {
        $test = new Test(
            'chrome',
            'http://example.com/complete',
            '/app/source/test.yml',
            '/app/target/GeneratedTest1234.php',
            ['step 1'],
            $position
        );

        $test->setState($state);

        return $test;
    }
}
