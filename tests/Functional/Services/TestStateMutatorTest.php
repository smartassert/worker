<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Enum\TestState;
use App\Enum\WorkerEventOutcome;
use App\Event\EmittableEvent\StepEvent;
use App\Model\Document\Step;
use App\Services\TestStateMutator;
use App\Tests\Model\TestSetup;
use App\Tests\Services\TestTestFactory;
use App\Tests\Services\TestTestMutator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TestStateMutatorTest extends WebTestCase
{
    private TestStateMutator $mutator;
    private EventDispatcherInterface $eventDispatcher;
    private TestTestMutator $testMutator;
    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $testStateMutator = self::getContainer()->get(TestStateMutator::class);
        \assert($testStateMutator instanceof TestStateMutator);
        $this->mutator = $testStateMutator;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $testMutator = self::getContainer()->get(TestTestMutator::class);
        \assert($testMutator instanceof TestTestMutator);
        $this->testMutator = $testMutator;

        $testTestFactory = self::getContainer()->get(TestTestFactory::class);
        \assert($testTestFactory instanceof TestTestFactory);
        $this->test = $testTestFactory->create(new TestSetup());
    }

    #[DataProvider('setCompleteIfRunningDataProvider')]
    public function testSetCompleteIfRunning(TestState $initialState, TestState $expectedState): void
    {
        $this->testMutator->setState($this->test, $initialState);
        self::assertSame($initialState, $this->test->getState());

        $this->mutator->setCompleteIfRunning($this->test);

        self::assertSame($expectedState, $this->test->getState());
    }

    /**
     * @return array<mixed>
     */
    public static function setCompleteIfRunningDataProvider(): array
    {
        return [
            TestState::AWAITING->value => [
                'initialState' => TestState::AWAITING,
                'expectedState' => TestState::AWAITING,
            ],
            TestState::RUNNING->value => [
                'initialState' => TestState::RUNNING,
                'expectedState' => TestState::COMPLETE,
            ],
            TestState::COMPLETE->value => [
                'initialState' => TestState::COMPLETE,
                'expectedState' => TestState::COMPLETE,
            ],
            TestState::FAILED->value => [
                'initialState' => TestState::FAILED,
                'expectedState' => TestState::FAILED,
            ],
            TestState::CANCELLED->value => [
                'initialState' => TestState::CANCELLED,
                'expectedState' => TestState::CANCELLED,
            ],
        ];
    }

    /**
     * @param array<mixed> $documentData
     */
    #[DataProvider('handleStepFailedEventDataProvider')]
    public function testSetFailedFromStepFailedEventEvent(array $documentData, TestState $expectedState): void
    {
        $this->doTestExecuteDocumentReceivedEventDrivenTest(
            $documentData,
            $expectedState,
            function (StepEvent $event) {
                $this->mutator->setFailedFromStepFailedEvent($event);
            }
        );
    }

    /**
     * @param array<mixed> $documentData
     */
    #[DataProvider('handleStepFailedEventDataProvider')]
    public function testSubscribesToStepFailedEvent(array $documentData, TestState $expectedState): void
    {
        $this->doTestExecuteDocumentReceivedEventDrivenTest(
            $documentData,
            $expectedState,
            function (StepEvent $event) {
                $this->eventDispatcher->dispatch($event);
            }
        );
    }

    /**
     * @return array<mixed>
     */
    public static function handleStepFailedEventDataProvider(): array
    {
        return [
            'step failed' => [
                'documentData' => [
                    'type' => 'step',
                    'status' => 'failed',
                ],
                'expectedState' => TestState::FAILED,
            ],
        ];
    }

    /**
     * @param array<mixed> $documentData
     */
    private function doTestExecuteDocumentReceivedEventDrivenTest(
        array $documentData,
        TestState $expectedState,
        callable $execute
    ): void {
        self::assertSame(TestState::AWAITING, $this->test->getState());

        $event = new StepEvent(
            $this->test,
            new Step('step name', $documentData),
            'test.yml',
            'step name',
            WorkerEventOutcome::FAILED
        );
        $execute($event);

        self::assertSame($expectedState, $this->test->getState());
    }
}
