<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventType;
use App\Event\JobTimeoutEvent;
use App\Event\StepEvent;
use App\Model\Document\Step;
use App\Repository\TestRepository;
use App\Services\TestCanceller;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\TestSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestTestFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\YamlDocument\Document;

class TestCancellerTest extends AbstractBaseFunctionalTest
{
    private TestCanceller $testCanceller;
    private EventDispatcherInterface $eventDispatcher;
    private TestTestFactory $testFactory;
    private TestRepository $testRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $testCanceller = self::getContainer()->get(TestCanceller::class);
        \assert($testCanceller instanceof TestCanceller);
        $this->testCanceller = $testCanceller;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $testFactory = self::getContainer()->get(TestTestFactory::class);
        \assert($testFactory instanceof TestTestFactory);
        $this->testFactory = $testFactory;

        $testRepository = self::getContainer()->get(TestRepository::class);
        \assert($testRepository instanceof TestRepository);
        $this->testRepository = $testRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider cancelAwaitingDataProvider
     *
     * @param TestState[] $states
     * @param TestState[] $expectedStates
     */
    public function testCancelAwaiting(array $states, array $expectedStates): void
    {
        $this->createTestsWithStates($states);

        $this->testCanceller->cancelAwaiting();
        $this->assertTestStates($expectedStates);
    }

    /**
     * @return array<mixed>
     */
    public function cancelAwaitingDataProvider(): array
    {
        return [
            'no tests' => [
                'states' => [],
                'expectedStates' => [],
            ],
            'no awaiting tests' => [
                'states' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                    TestState::RUNNING,
                    TestState::RUNNING,
                ],
                'expectedStates' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                    TestState::RUNNING,
                    TestState::RUNNING,
                ],
            ],
            'all awaiting tests' => [
                'states' => [
                    TestState::AWAITING,
                    TestState::AWAITING,
                ],
                'expectedStates' => [
                    TestState::CANCELLED,
                    TestState::CANCELLED,
                ],
            ],
            'mixed' => [
                'states' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                    TestState::RUNNING,
                    TestState::AWAITING,
                ],
                'expectedStates' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                    TestState::RUNNING,
                    TestState::CANCELLED,
                ],
            ],
        ];
    }

    /**
     * @dataProvider cancelUnfinishedDataProvider
     *
     * @param TestState[] $states
     * @param TestState[] $expectedStates
     */
    public function testCancelUnfinished(
        array $states,
        array $expectedStates
    ): void {
        $this->createTestsWithStates($states);

        $this->testCanceller->cancelUnfinished();
        $this->assertTestStates($expectedStates);
    }

    /**
     * @return array<mixed>
     */
    public function cancelUnfinishedDataProvider(): array
    {
        return [
            'no tests' => [
                'states' => [],
                'expectedStates' => [],
            ],
            'no unfinished tests' => [
                'states' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                ],
                'expectedStates' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                ],
            ],
            'all unfinished tests' => [
                'states' => [
                    TestState::AWAITING,
                    TestState::RUNNING,
                ],
                'expectedStates' => [
                    TestState::CANCELLED,
                    TestState::CANCELLED,
                ],
            ],
            'mixed' => [
                'states' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                    TestState::RUNNING,
                    TestState::AWAITING,
                ],
                'expectedStates' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                    TestState::CANCELLED,
                    TestState::CANCELLED,
                ],
            ],
        ];
    }

    /**
     * @dataProvider cancelAwaitingFromStepFailedEventDataProvider
     *
     * @param TestState[] $states
     * @param TestState[] $expectedStates
     */
    public function testCancelAwaitingFromStepFailedEvent(
        array $states,
        array $expectedStates
    ): void {
        $this->doTestStepFailedEventDrivenTest(
            $states,
            function (StepEvent $event) {
                $this->testCanceller->cancelAwaitingFromTestFailedEvent($event);
            },
            $expectedStates
        );
    }

    /**
     * @dataProvider cancelAwaitingFromStepFailedEventDataProvider
     *
     * @param TestState[] $states
     * @param TestState[] $expectedStates
     */
    public function testSubscribesToStepFailedEvent(
        array $states,
        array $expectedStates
    ): void {
        $this->doTestStepFailedEventDrivenTest(
            $states,
            function (StepEvent $event) {
                $this->eventDispatcher->dispatch($event);
            },
            $expectedStates
        );
    }

    /**
     * @return array<mixed>
     */
    public function cancelAwaitingFromStepFailedEventDataProvider(): array
    {
        return [
            'no awaiting tests, test failed' => [
                'states' => [
                    TestState::FAILED,
                    TestState::COMPLETE,
                ],
                'expectedStates' => [
                    TestState::FAILED,
                    TestState::COMPLETE,
                ],
            ],
            'has awaiting tests, test failed' => [
                'states' => [
                    TestState::FAILED,
                    TestState::AWAITING,
                    TestState::AWAITING,
                ],
                'expectedStates' => [
                    TestState::FAILED,
                    TestState::CANCELLED,
                    TestState::CANCELLED,
                ],
            ],
        ];
    }

    /**
     * @dataProvider subscribesToJobTimeoutEventDataProvider
     *
     * @param TestState[] $states
     * @param TestState[] $expectedStates
     */
    public function testSubscribesToJobTimeoutEvent(
        array $states,
        array $expectedStates
    ): void {
        $tests = $this->createTestsWithStates($states);
        $test = $tests[0];
        self::assertInstanceOf(Test::class, $test);

        $event = new JobTimeoutEvent(10);
        $this->eventDispatcher->dispatch($event);

        $this->assertTestStates($expectedStates);
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToJobTimeoutEventDataProvider(): array
    {
        return [
            'no unfinished tests' => [
                'states' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                ],
                'expectedStates' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                ],
            ],
            'has unfinished tests' => [
                'states' => [
                    TestState::AWAITING,
                    TestState::RUNNING,
                ],
                'expectedStates' => [
                    TestState::CANCELLED,
                    TestState::CANCELLED,
                ],
            ],
            'mixed' => [
                'states' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                    TestState::AWAITING,
                    TestState::RUNNING,
                ],
                'expectedStates' => [
                    TestState::COMPLETE,
                    TestState::CANCELLED,
                    TestState::FAILED,
                    TestState::CANCELLED,
                    TestState::CANCELLED,
                ],
            ],
        ];
    }

    /**
     * @param TestState[] $states
     * @param TestState[] $expectedStates
     */
    private function doTestStepFailedEventDrivenTest(
        array $states,
        callable $execute,
        array $expectedStates
    ): void {
        $tests = $this->createTestsWithStates($states);
        $test = $tests[0];
        self::assertInstanceOf(Test::class, $test);

        $document = new Document();

        $event = new StepEvent(
            WorkerEventOutcome::FAILED,
            WorkerEventType::STEP_FAILED,
            new Step($document),
            '',
            $test
        );
        $execute($event);

        $this->assertTestStates($expectedStates);
    }

    /**
     * @param TestState[] $states
     *
     * @return Test[]
     */
    private function createTestsWithStates(array $states): array
    {
        $tests = [];

        foreach ($states as $state) {
            $tests[] = $this->testFactory->create((new TestSetup())->withState($state));
        }

        return $tests;
    }

    /**
     * @param TestState[] $expectedStates
     */
    private function assertTestStates(array $expectedStates): void
    {
        $tests = $this->testRepository->findAll();
        $states = [];

        foreach ($tests as $test) {
            $states[] = $test->getState();
        }

        self::assertSame($expectedStates, $states);
    }
}
