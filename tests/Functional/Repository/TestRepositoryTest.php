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
     */
    public function testFindNextAwaitingIdNotNull(EnvironmentSetup $setup, int $nextAwaitingIndex): void
    {
        $this->environmentFactory->create($setup);

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
        return [
            'awaiting1' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::AWAITING),
                    ]),
                'expectedNextAwaitingIndex' => 0,
            ],
            'awaiting1, awaiting2' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(1),
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(2),
                    ]),
                'expectedNextAwaitingIndex' => 0,
            ],
            'awaiting2, awaiting1' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(2),
                        (new TestSetup())
                            ->withState(TestState::AWAITING)
                            ->withPosition(1),
                    ]),
                'expectedNextAwaitingIndex' => 1,
            ],
            'running, failed, awaiting1, complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::RUNNING),
                        (new TestSetup())->withState(TestState::FAILED),
                        (new TestSetup())->withState(TestState::AWAITING),
                        (new TestSetup())->withState(TestState::COMPLETE),
                    ]),
                'expectedNextAwaitingIndex' => 2,
            ],
        ];
    }

    /**
     * @dataProvider findUnfinishedCountDataProvider
     */
    public function testFindUnfinishedCount(EnvironmentSetup $setup, int $expectedUnfinishedCount): void
    {
        $this->environmentFactory->create($setup);

        self::assertSame($expectedUnfinishedCount, $this->repository->findUnfinishedCount());
    }

    /**
     * @return array<mixed>
     */
    public function findUnfinishedCountDataProvider(): array
    {
        return [
            'empty' => [
                'setup' => new EnvironmentSetup(),
                'expectedUnfinishedCount' => 0,
            ],
            'awaiting1' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::AWAITING),
                    ]),
                'expectedUnfinishedCount' => 1,
            ],
            'awaiting1, awaiting2' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::AWAITING),
                        (new TestSetup())->withState(TestState::AWAITING),
                    ]),
                'expectedUnfinishedCount' => 2,
            ],
            'awaiting1, running' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::AWAITING),
                        (new TestSetup())->withState(TestState::RUNNING),
                    ]),
                'expectedUnfinishedCount' => 2,
            ],
            'all states' => [
                'setup' => (new EnvironmentSetup())
                    ->withTestSetups([
                        (new TestSetup())->withState(TestState::AWAITING),
                        (new TestSetup())->withState(TestState::AWAITING),
                        (new TestSetup())->withState(TestState::RUNNING),
                        (new TestSetup())->withState(TestState::FAILED),
                        (new TestSetup())->withState(TestState::COMPLETE),
                        (new TestSetup())->withState(TestState::CANCELLED),
                    ]),
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
        $this->environmentFactory->create($setup);

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
}
