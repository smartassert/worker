<?php

declare(strict_types=1);

namespace App\Tests\Functional\EventSubscriber;

use App\Event\TestExecuteDocumentReceivedEvent;
use App\EventSubscriber\TestExecuteDocumentReceivedEventSubscriber;
use App\Message\SendCallback;
use App\Model\Callback\ExecuteDocumentReceived;
use App\Tests\Functional\AbstractBaseFunctionalTest;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use webignition\YamlDocument\Document;

class TestExecuteDocumentReceivedEventSubscriberTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private TestExecuteDocumentReceivedEventSubscriber $eventSubscriber;
    private InMemoryTransport $messengerTransport;
    private Document $document;
    private TestExecuteDocumentReceivedEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $eventSubscriber = self::$container->get(TestExecuteDocumentReceivedEventSubscriber::class);
        if ($eventSubscriber instanceof TestExecuteDocumentReceivedEventSubscriber) {
            $this->eventSubscriber = $eventSubscriber;
        }

        $messengerTransport = self::$container->get('messenger.transport.async');
        if ($messengerTransport instanceof InMemoryTransport) {
            $this->messengerTransport = $messengerTransport;
        }

        $this->document = \Mockery::mock(Document::class);
        $this->document
            ->shouldReceive('parse')
            ->andReturn([
                'key1' => 'value1',
                'key2' => 'value2',
            ]);

        $this->event = new TestExecuteDocumentReceivedEvent($this->document);
    }

    public function testDispatchSendCallbackMessage()
    {
        $this->eventSubscriber->dispatchSendCallbackMessage($this->event);

        $this->assertMessageTransportQueue($this->document);
    }

    public function testIntegration()
    {
        self::assertCount(0, $this->messengerTransport->get());

        $eventDispatcher = self::$container->get(EventDispatcherInterface::class);
        if ($eventDispatcher instanceof EventDispatcherInterface) {
            $eventDispatcher->dispatch($this->event, TestExecuteDocumentReceivedEvent::NAME);
        }

        $this->assertMessageTransportQueue($this->document);
    }

    private function assertMessageTransportQueue(Document $document): void
    {
        $queue = $this->messengerTransport->get();
        self::assertCount(1, $queue);
        self::assertIsArray($queue);

        $expectedCallback = new ExecuteDocumentReceived($document);
        $expectedQueuedMessage = new SendCallback($expectedCallback);

        self::assertEquals($expectedQueuedMessage, $queue[0]->getMessage());
    }
}