<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Event\EmittableEvent\SourceCompilationPassedEvent;
use App\Message\CompileSourceMessage;
use App\Services\CompilationWorkflowHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\Asserter\MessengerAsserter;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;

class CompilationWorkflowHandlerTest extends AbstractBaseFunctionalTest
{
    private CompilationWorkflowHandler $handler;
    private MessengerAsserter $messengerAsserter;
    private EnvironmentFactory $environmentFactory;
    private TransportInterface $messengerTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $compilationWorkflowHandler = self::getContainer()->get(CompilationWorkflowHandler::class);
        \assert($compilationWorkflowHandler instanceof CompilationWorkflowHandler);
        $this->handler = $compilationWorkflowHandler;

        $messengerAsserter = self::getContainer()->get(MessengerAsserter::class);
        \assert($messengerAsserter instanceof MessengerAsserter);
        $this->messengerAsserter = $messengerAsserter;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
        }

        $messengerTransport = self::getContainer()->get('messenger.transport.async');
        \assert($messengerTransport instanceof TransportInterface);
        $this->messengerTransport = $messengerTransport;
    }

    /**
     * @dataProvider dispatchNextCompileSourceMessageNoMessageDispatchedDataProvider
     */
    public function testDispatchNextCompileSourceMessageNoMessageDispatched(EnvironmentSetup $setup): void
    {
        $this->environmentFactory->create($setup);

        $this->handler->dispatchNextCompileSourceMessage(\Mockery::mock(SourceCompilationPassedEvent::class));

        self::assertCount(0, $this->messengerTransport->get());
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
                        (new TestSetup())->withSource('Test/test1.yml')
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

        $this->handler->dispatchNextCompileSourceMessage(\Mockery::mock(SourceCompilationPassedEvent::class));

        self::assertCount(1, $this->messengerTransport->get());
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
}
