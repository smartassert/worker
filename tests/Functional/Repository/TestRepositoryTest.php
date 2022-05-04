<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\EntityInterface;
use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Repository\TestRepository;
use App\Services\EntityStore\TestConfigurationStore;
use App\Tests\Services\EntityRemover;
use webignition\ObjectReflector\ObjectReflector;

class TestRepositoryTest extends AbstractEntityRepositoryTest
{
    private TestRepository $repository;
    private TestConfigurationStore $testConfigurationStore;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = self::getContainer()->get(TestRepository::class);
        \assert($repository instanceof TestRepository);
        $this->repository = $repository;

        $testConfigurationStore = self::getContainer()->get(TestConfigurationStore::class);
        \assert($testConfigurationStore instanceof TestConfigurationStore);
        $this->testConfigurationStore = $testConfigurationStore;

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
            'position1' => Test::STATE_AWAITING,
            'position2' => Test::STATE_AWAITING,
            'position3' => Test::STATE_AWAITING,
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
                    Test::STATE_RUNNING,
                    Test::STATE_FAILED,
                    Test::STATE_COMPLETE,
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
            'awaiting1' => Test::STATE_AWAITING,
            'awaiting2' => Test::STATE_AWAITING,
            'running' => Test::STATE_RUNNING,
            'failed' => Test::STATE_FAILED,
            'complete' => Test::STATE_COMPLETE,
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
     * @dataProvider findAllAwaitingDataProvider
     *
     * @param Test[] $tests
     * @param Test[] $expectedAwaitingTests
     */
    public function testFindAllAwaiting(array $tests, array $expectedAwaitingTests): void
    {
        foreach ($tests as $test) {
            $this->persistEntity($test);
        }

        self::assertSame($expectedAwaitingTests, $this->repository->findAllAwaiting());
    }

    /**
     * @return array<mixed>
     */
    public function findAllAwaitingDataProvider(): array
    {
        $tests = $this->createTestsWithStates([
            'awaiting1' => Test::STATE_AWAITING,
            'awaiting2' => Test::STATE_AWAITING,
            'running' => Test::STATE_RUNNING,
        ]);

        return [
            'empty' => [
                'tests' => [],
                'expectedAwaitingTests' => [],
            ],
            'awaiting1' => [
                'tests' => [
                    $tests['awaiting1'],
                ],
                'expectedAwaitingTests' => [
                    $tests['awaiting1'],
                ],
            ],
            'awaiting2' => [
                'tests' => [
                    $tests['awaiting2'],
                ],
                'expectedAwaitingTests' => [
                    $tests['awaiting2'],
                ],
            ],
            'awaiting1, awaiting2' => [
                'tests' => [
                    $tests['awaiting1'],
                    $tests['awaiting2'],
                ],
                'expectedAwaitingTests' => [
                    $tests['awaiting1'],
                    $tests['awaiting2'],
                ],
            ],
            'running' => [
                'tests' => [
                    $tests['running'],
                ],
                'expectedAwaitingTests' => [],
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
            'awaiting1' => Test::STATE_AWAITING,
            'awaiting2' => Test::STATE_AWAITING,
            'running' => Test::STATE_RUNNING,
            'failed' => Test::STATE_FAILED,
            'complete' => Test::STATE_COMPLETE,
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

    protected function persistEntity(EntityInterface $entity): void
    {
        if ($entity instanceof Test) {
            ObjectReflector::setProperty(
                $entity,
                Test::class,
                'configuration',
                $this->testConfigurationStore->get($entity->getConfiguration())
            );
        }

        parent::persistEntity($entity);
    }

    /**
     * @param array<Test::STATE_*> $states
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

    /**
     * @param Test::STATE_* $state
     */
    private function createTestWithStateAndPosition(string $state, int $position): Test
    {
        $test = Test::create(
            TestConfiguration::create('chrome', 'http://example.com/complete'),
            '',
            '',
            1,
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
