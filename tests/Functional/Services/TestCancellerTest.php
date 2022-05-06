<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Event\JobTimeoutEvent;
use App\Event\StepFailedEvent;
use App\Model\Document\Step;
use App\Services\TestCanceller;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\TestSetup;
use App\Tests\Services\Asserter\TestEntityAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\TestTestFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\YamlDocument\Document;

class TestCancellerTest extends AbstractBaseFunctionalTest
{
    private TestCanceller $testCanceller;
    private EventDispatcherInterface $eventDispatcher;
    private TestTestFactory $testFactory;
    private TestEntityAsserter $testEntityAsserter;

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

        $testEntityAsserter = self::getContainer()->get(TestEntityAsserter::class);
        \assert($testEntityAsserter instanceof TestEntityAsserter);
        $this->testEntityAsserter = $testEntityAsserter;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider cancelAwaitingDataProvider
     *
     * @param array<Test::STATE_*> $states
     * @param array<Test::STATE_*> $expectedStates
     */
    public function testCancelAwaiting(
        array $states,
        array $expectedStates
    ): void {
        $this->createTestsWithStates($states);

        $this->testCanceller->cancelAwaiting();
        $this->testEntityAsserter->assertTestStates($expectedStates);
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
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                    Test::STATE_RUNNING,
                    Test::STATE_RUNNING,
                ],
                'expectedStates' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                    Test::STATE_RUNNING,
                    Test::STATE_RUNNING,
                ],
            ],
            'all awaiting tests' => [
                'states' => [
                    Test::STATE_AWAITING,
                    Test::STATE_AWAITING,
                ],
                'expectedStates' => [
                    Test::STATE_CANCELLED,
                    Test::STATE_CANCELLED,
                ],
            ],
            'mixed' => [
                'states' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                    Test::STATE_RUNNING,
                    Test::STATE_AWAITING,
                ],
                'expectedStates' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                    Test::STATE_RUNNING,
                    Test::STATE_CANCELLED,
                ],
            ],
        ];
    }

    /**
     * @dataProvider cancelUnfinishedDataProvider
     *
     * @param array<Test::STATE_*> $states
     * @param array<Test::STATE_*> $expectedStates
     */
    public function testCancelUnfinished(
        array $states,
        array $expectedStates
    ): void {
        $this->createTestsWithStates($states);

        $this->testCanceller->cancelUnfinished();
        $this->testEntityAsserter->assertTestStates($expectedStates);
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
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                ],
                'expectedStates' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                ],
            ],
            'all unfinished tests' => [
                'states' => [
                    Test::STATE_AWAITING,
                    Test::STATE_RUNNING,
                ],
                'expectedStates' => [
                    Test::STATE_CANCELLED,
                    Test::STATE_CANCELLED,
                ],
            ],
            'mixed' => [
                'states' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                    Test::STATE_RUNNING,
                    Test::STATE_AWAITING,
                ],
                'expectedStates' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                    Test::STATE_CANCELLED,
                    Test::STATE_CANCELLED,
                ],
            ],
        ];
    }

    /**
     * @dataProvider cancelAwaitingFromStepFailedEventDataProvider
     *
     * @param array<Test::STATE_*> $states
     * @param array<Test::STATE_*> $expectedStates
     */
    public function testCancelAwaitingFromStepFailedEvent(
        array $states,
        array $expectedStates
    ): void {
        $this->doTestStepFailedEventDrivenTest(
            $states,
            function (StepFailedEvent $event) {
                $this->testCanceller->cancelAwaitingFromTestFailedEvent($event);
            },
            $expectedStates
        );
    }

    /**
     * @dataProvider cancelAwaitingFromStepFailedEventDataProvider
     *
     * @param array<Test::STATE_*> $states
     * @param array<Test::STATE_*> $expectedStates
     */
    public function testSubscribesToStepFailedEvent(
        array $states,
        array $expectedStates
    ): void {
        $this->doTestStepFailedEventDrivenTest(
            $states,
            function (StepFailedEvent $event) {
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
                    Test::STATE_FAILED,
                    Test::STATE_COMPLETE,
                ],
                'expectedStates' => [
                    Test::STATE_FAILED,
                    Test::STATE_COMPLETE,
                ],
            ],
            'has awaiting tests, test failed' => [
                'states' => [
                    Test::STATE_FAILED,
                    Test::STATE_AWAITING,
                    Test::STATE_AWAITING,
                ],
                'expectedStates' => [
                    Test::STATE_FAILED,
                    Test::STATE_CANCELLED,
                    Test::STATE_CANCELLED,
                ],
            ],
        ];
    }

    /**
     * @dataProvider subscribesToJobTimeoutEventDataProvider
     *
     * @param array<Test::STATE_*> $states
     * @param array<Test::STATE_*> $expectedStates
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

        $this->testEntityAsserter->assertTestStates($expectedStates);
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToJobTimeoutEventDataProvider(): array
    {
        return [
            'no unfinished tests' => [
                'states' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                ],
                'expectedStates' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                ],
            ],
            'has unfinished tests' => [
                'states' => [
                    Test::STATE_AWAITING,
                    Test::STATE_RUNNING,
                ],
                'expectedStates' => [
                    Test::STATE_CANCELLED,
                    Test::STATE_CANCELLED,
                ],
            ],
            'mixed' => [
                'states' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                    Test::STATE_AWAITING,
                    Test::STATE_RUNNING,
                ],
                'expectedStates' => [
                    Test::STATE_COMPLETE,
                    Test::STATE_CANCELLED,
                    Test::STATE_FAILED,
                    Test::STATE_CANCELLED,
                    Test::STATE_CANCELLED,
                ],
            ],
        ];
    }

    /**
     * @param array<Test::STATE_*> $states
     * @param array<Test::STATE_*> $expectedStates
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

        $event = new StepFailedEvent($test, new Step($document), '');
        $execute($event);

        $this->testEntityAsserter->assertTestStates($expectedStates);
    }

    /**
     * @param array<Test::STATE_*> $states
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
}
