<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackInterface;
use App\Services\EventCallbackFactory\EventCallbackFactoryInterface;
use App\Tests\AbstractBaseFunctionalTest;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEventCallbackFactoryTest extends AbstractBaseFunctionalTest
{
    private EventCallbackFactoryInterface $callbackFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $callbackFactory = $this->getCallbackFactory();
        if ($callbackFactory instanceof EventCallbackFactoryInterface) {
            $this->callbackFactory = $callbackFactory;
        }
    }

    /**
     * @return array<mixed>
     */
    abstract public function createDataProvider(): array;

    public function testCreateForEventUnsupportedEvent(): void
    {
        self::assertNull($this->callbackFactory->createForEvent(new Event()));
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreateForEvent(Event $event, CallbackInterface $expectedCallback): void
    {
        $callback = $this->callbackFactory->createForEvent($event);

        self::assertInstanceOf(CallbackInterface::class, $callback);

        if ($callback instanceof CallbackInterface) {
            self::assertNotNull($callback->getId());
            self::assertSame($expectedCallback->getType(), $callback->getType());
            self::assertSame($expectedCallback->getPayload(), $callback->getPayload());
        }
    }

    abstract protected function getCallbackFactory(): ?EventCallbackFactoryInterface;
}
