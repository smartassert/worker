<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Test;
use App\Entity\TestState;
use App\Event\StepFailedEvent;
use App\Model\Document\Step;
use App\Services\TestStateMutator;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\TestSetup;
use App\Tests\Services\TestTestFactory;
use App\Tests\Services\TestTestMutator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\YamlDocument\Document;

class TestStateMutatorTest extends AbstractBaseFunctionalTest
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

    /**
     * @dataProvider setCompleteIfRunningDataProvider
     */
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
    public function setCompleteIfRunningDataProvider(): array
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
     * @dataProvider handleStepFailedEventDataProvider
     */
    public function testSetFailedFromStepFailedEventEvent(Document $document, TestState $expectedState): void
    {
        $this->doTestExecuteDocumentReceivedEventDrivenTest(
            $document,
            $expectedState,
            function (StepFailedEvent $event) {
                $this->mutator->setFailedFromStepFailedEvent($event);
            }
        );
    }

    /**
     * @dataProvider handleStepFailedEventDataProvider
     */
    public function testSubscribesToStepFailedEvent(Document $document, TestState $expectedState): void
    {
        $this->doTestExecuteDocumentReceivedEventDrivenTest(
            $document,
            $expectedState,
            function (StepFailedEvent $event) {
                $this->eventDispatcher->dispatch($event);
            }
        );
    }

    /**
     * @return array<mixed>
     */
    public function handleStepFailedEventDataProvider(): array
    {
        return [
            'step failed' => [
                'document' => new Document('{ type: step, status: failed }'),
                'expectedState' => TestState::FAILED,
            ],
        ];
    }

    private function doTestExecuteDocumentReceivedEventDrivenTest(
        Document $document,
        TestState $expectedState,
        callable $execute
    ): void {
        self::assertSame(TestState::AWAITING, $this->test->getState());

        $event = new StepFailedEvent(new Step($document), '', $this->test);
        $execute($event);

        self::assertSame($expectedState, $this->test->getState());
    }
}
