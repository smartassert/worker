<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EventCallbackFactory;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Job;
use App\Services\EventCallbackFactory\EventCallbackFactoryInterface;
use App\Tests\AbstractBaseFunctionalTest;
use Symfony\Contracts\EventDispatcher\Event;
use webignition\ObjectReflector\ObjectReflector;

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
        self::assertNull($this->callbackFactory->createForEvent(new Job(), new Event()));
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreateForEvent(Event $event, CallbackEntity $expectedCallback): void
    {
        $jobLabel = md5((string) rand());
        $job = Job::create($jobLabel, '', 600);

        $callback = $this->callbackFactory->createForEvent($job, $event);

        $expectedReferenceSource = str_replace('{{ job_label }}', $jobLabel, $expectedCallback->getReference());
        ObjectReflector::setProperty(
            $expectedCallback,
            CallbackEntity::class,
            'reference',
            md5($expectedReferenceSource)
        );

        self::assertInstanceOf(CallbackEntity::class, $callback);
        self::assertNotNull($callback->getId());
        self::assertSame($expectedCallback->getType(), $callback->getType());
        self::assertSame($expectedCallback->getReference(), $callback->getReference());
        self::assertSame($expectedCallback->getPayload(), $callback->getPayload());
    }

    abstract protected function getCallbackFactory(): ?EventCallbackFactoryInterface;
}
