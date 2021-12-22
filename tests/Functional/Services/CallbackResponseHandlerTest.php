<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Event\CallbackHttpErrorEvent;
use App\Model\Callback\DelayedCallback;
use App\Model\StampCollection;
use App\Services\CallbackResponseHandler;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\TestCallback;
use App\Tests\Services\CallbackHttpErrorEventSubscriber;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class CallbackResponseHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private CallbackResponseHandler $callbackResponseHandler;
    private CallbackHttpErrorEventSubscriber $httpErrorEventSubscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $callbackResponseHandler = self::getContainer()->get(CallbackResponseHandler::class);
        \assert($callbackResponseHandler instanceof CallbackResponseHandler);
        $this->callbackResponseHandler = $callbackResponseHandler;

        $httpErrorEventSubscriber = self::getContainer()->get(CallbackHttpErrorEventSubscriber::class);
        \assert($httpErrorEventSubscriber instanceof CallbackHttpErrorEventSubscriber);
        $this->httpErrorEventSubscriber = $httpErrorEventSubscriber;
    }

    /**
     * @dataProvider handleDataProvider
     *
     * @param ClientExceptionInterface|ResponseInterface $context
     */
    public function testHandle(object $context, StampCollection $expectedCallbackStamps): void
    {
        $callback = new TestCallback();
        self::assertSame(0, $callback->getRetryCount());

        $this->callbackResponseHandler->handle($callback, $context);

        $event = $this->httpErrorEventSubscriber->getEvent();
        self::assertInstanceOf(CallbackHttpErrorEvent::class, $event);

        $eventCallback = $event->getCallback();
        self::assertInstanceOf(DelayedCallback::class, $eventCallback);
        self::assertSame($eventCallback->getEntity(), $callback->getEntity());
        self::assertSame($context, $event->getContext());
        self::assertSame(1, $callback->getRetryCount());

        if ($eventCallback instanceof DelayedCallback) {
            self::assertEquals($expectedCallbackStamps, $eventCallback->getStamps());
        }
    }

    /**
     * @return array[]
     */
    public function handleDataProvider(): array
    {
        return [
            'non-success response' => [
                'context' => new Response(404),
                'expectedCallbackStamps' => new StampCollection([
                    new DelayStamp(1000),
                ]),
            ],
            'non-success response, 30 second delay in retry-after header' => [
                'context' => new Response(
                    503,
                    [
                        'retry-after' => '30',
                    ]
                ),
                'expectedCallbackStamps' => new StampCollection([
                    new DelayStamp(30000),
                ]),
            ],
            'exception' => [
                'context' => \Mockery::mock(ConnectException::class),
                'expectedCallbackStamps' => new StampCollection([
                    new DelayStamp(1000),
                ]),
            ],
        ];
    }
}
