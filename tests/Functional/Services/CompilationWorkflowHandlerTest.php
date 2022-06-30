<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Event\EventInterface;
use App\Event\JobStartedEvent;
use App\Event\SourceCompilationPassedEvent;
use App\Message\CompileSourceMessage;
use App\Message\TimeoutCheckMessage;
use App\MessageDispatcher\DeliverEventMessageDispatcher;
use App\Services\CompilationWorkflowHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\EventListenerRemover;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use webignition\BasilCompilerModels\Model\TestManifestCollection;

class CompilationWorkflowHandlerTest extends AbstractBaseFunctionalTest
{
    private CompilationWorkflowHandler $handler;
    private EventDispatcherInterface $eventDispatcher;
    private MessengerAsserter $messengerAsserter;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $compilationWorkflowHandler = self::getContainer()->get(CompilationWorkflowHandler::class);
        \assert($compilationWorkflowHandler instanceof CompilationWorkflowHandler);
        $this->handler = $compilationWorkflowHandler;

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);
        $this->eventDispatcher = $eventDispatcher;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $eventListenerRemover = self::getContainer()->get(EventListenerRemover::class);
        \assert($eventListenerRemover instanceof EventListenerRemover);
        $eventListenerRemover->remove([
            DeliverEventMessageDispatcher::class => [
                JobStartedEvent::class => ['dispatchForEvent'],
                SourceCompilationPassedEvent::class => ['dispatchForEvent'],
            ],
        ]);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider dispatchNextCompileSourceMessageNoMessageDispatchedDataProvider
     */
    public function testDispatchNextCompileSourceMessageNoMessageDispatched(EnvironmentSetup $setup): void
    {
        $this->environmentFactory->create($setup);

        $this->handler->dispatchNextCompileSourceMessage();

        $this->messengerAsserter->assertQueueIsEmpty();
    }

    /**
     * @return array<mixed>
     */
    public function dispatchNextCompileSourceMessageNoMessageDispatchedDataProvider(): array
    {
        return [
            'no sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup()),
            ],
            'no non-compiled sources' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withTestSetups([
                        (new TestSetup())->withSource('/app/source/Test/test1.yml')
                    ]),
            ],
        ];
    }

    /**
     * @dataProvider dispatchNextCompileSourceMessageMessageDispatchedDataProvider
     */
    public function testDispatchNextCompileSourceMessageMessageDispatched(
        EnvironmentSetup $setup,
        CompileSourceMessage $expectedQueuedMessage
    ): void {
        $this->environmentFactory->create($setup);

        $this->handler->dispatchNextCompileSourceMessage();

        $this->messengerAsserter->assertQueueCount(1);
        $this->messengerAsserter->assertMessageAtPositionEquals(0, $expectedQueuedMessage);
    }

    /**
     * @return array<mixed>
     */
    public function dispatchNextCompileSourceMessageMessageDispatchedDataProvider(): array
    {
        return [
            'no sources compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ]),
                'expectedQueuedMessage' => new CompileSourceMessage('Test/test1.yml'),
            ],
            'all but one sources compiled' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(new JobSetup())
                    ->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())->withSource('Test/test1.yml'),
                    ]),
                'expectedQueuedMessage' => new CompileSourceMessage('Test/test2.yml'),
            ],
        ];
    }

    /**
     * @dataProvider subscribesToEventsDataProvider
     *
     * @param object[] $expectedQueuedMessages
     */
    public function testSubscribesToEvents(EventInterface $event, array $expectedQueuedMessages): void
    {
        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withSourceSetups([
                (new SourceSetup())->withPath('Test/test1.yml'),
                (new SourceSetup())->withPath('Test/test2.yml'),
            ])
        ;

        $this->environmentFactory->create($environmentSetup);

        $this->messengerAsserter->assertQueueIsEmpty();

        $this->eventDispatcher->dispatch($event);

        $this->messengerAsserter->assertQueueCount(count($expectedQueuedMessages));
        foreach ($expectedQueuedMessages as $messageIndex => $expectedQueuedMessage) {
            $this->messengerAsserter->assertMessageAtPositionEquals($messageIndex, $expectedQueuedMessage);
        }
    }

    /**
     * @return array<mixed>
     */
    public function subscribesToEventsDataProvider(): array
    {
        return [
            SourceCompilationPassedEvent::class => [
                'event' => new SourceCompilationPassedEvent(
                    '/app/source/Test/test1.yml',
                    new TestManifestCollection([])
                ),
                'expectedQueuedMessages' => [
                    new CompileSourceMessage('Test/test1.yml'),
                ],
            ],
            JobStartedEvent::class => [
                'event' => new JobStartedEvent([]),
                'expectedQueuedMessages' => [
                    new CompileSourceMessage('Test/test1.yml'),
                    new TimeoutCheckMessage(),
                ],
            ],
        ];
    }
}
