<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Enum\TestState;
use App\Repository\TestConfigurationRepository;
use App\Repository\TestRepository;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use webignition\ObjectReflector\ObjectReflector;

class TestRepositoryTest extends AbstractEntityRepositoryTest
{
    private TestRepository $repository;
    private TestConfigurationRepository $configurationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(TestRepository::class);
        \assert($repository instanceof TestRepository);
        $this->repository = $repository;

        $configurationRepository = self::getContainer()->get(TestConfigurationRepository::class);
        \assert($configurationRepository instanceof TestConfigurationRepository);
        $this->configurationRepository = $configurationRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider findMaxPositionDataProvider
     *
     * @param Test[] $tests
     */
    public function testFindMaxPosition(array $tests, ?int $expectedMaxPosition): void
    {
        foreach ($tests as $test) {
            $this->persistEntity($test);
        }

        self::assertSame($expectedMaxPosition, $this->repository->findMaxPosition());
    }

    /**
     * @return array<mixed>
     */
    public function findMaxPositionDataProvider(): array
    {
        $tests = $this->createTestsWithStates([
            'position1' => TestState::AWAITING,
            'position2' => TestState::AWAITING,
            'position3' => TestState::AWAITING,
        ]);

        return [
            'empty' => [
                'tests' => [],
                'expectedMaxPosition' => 0,
            ],
            'one test, position 1' => [
                'tests' => [
                    $tests['position1'],
                ],
                'expectedMaxPosition' => 1,
            ],
            'one test, position 3' => [
                'tests' => [
                    $tests['position3'],
                ],
                'expectedMaxPosition' => 3,
            ],
            'three tests, position 1, 2, 3' => [
                'tests' => [
                    $tests['position1'],
                    $tests['position2'],
                    $tests['position3'],
                ],
                'expectedMaxPosition' => 3,
            ],
            'three tests, position 3, 2, 1' => [
                'tests' => [
                    $tests['position3'],
                    $tests['position2'],
                    $tests['position1'],
                ],
                'expectedMaxPosition' => 3,
            ],
            'three tests, position 1, 3, 2' => [
                'tests' => [
                    $tests['position1'],
                    $tests['position3'],
                    $tests['position2'],
                ],
                'expectedMaxPosition' => 3,
            ],
        ];
    }

    /**
     * @dataProvider findNextAwaitingIdIsNullDataProvider
     *
     * @param Test[] $tests
     */
    public function testFindNextAwaitingIdIsNull(array $tests): void
    {
        foreach ($tests as $test) {
            $this->persistEntity($test);
        }

        self::assertNull($this->repository->findNextAwaitingId());
    }

    /**
     * @return array<mixed>
     */
    public function findNextAwaitingIdIsNullDataProvider(): array
    {
        return [
            'empty' => [
                'tests' => [],
            ],
            'running, failed, complete' => [
                'tests' => $this->createTestsWithStates([
                    TestState::RUNNING,
                    TestState::FAILED,
                    TestState::COMPLETE,
                ]),
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

        $allTests = $this->findAllTests();
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
        ];
    }

    protected function persistEntity(object $entity): void
    {
        if ($entity instanceof Test) {
            ObjectReflector::setProperty(
                $entity,
                Test::class,
                'configuration',
                $this->configurationRepository->get($entity->getConfiguration())
            );
        }

        parent::persistEntity($entity);
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
            TestConfiguration::create('chrome', 'http://example.com/complete'),
            '',
            '',
            ['step 1'],
            $position
        );

        $test->setState($state);

        return $test;
    }

    /**
     * @return Test[]
     */
    private function findAllTests(): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('Test')
            ->from(Test::class, 'Test')
        ;

        $query = $queryBuilder->getQuery();
        $results = $query->getResult();
        self::assertIsArray($results);

        return array_filter($results, function ($item) {
            return $item instanceof Test;
        });
    }
}
