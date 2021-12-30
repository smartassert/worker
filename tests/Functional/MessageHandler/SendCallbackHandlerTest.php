<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Callback\CallbackInterface;
use App\Message\SendCallbackMessage;
use App\MessageHandler\SendCallbackHandler;
use App\Model\SendCallbackResult;
use App\Repository\CallbackRepository;
use App\Services\CallbackResponseHandler;
use App\Services\CallbackSender;
use App\Services\CallbackStateMutator;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockCallbackResponseHandler;
use App\Tests\Mock\Services\MockCallbackSender;
use App\Tests\Model\CallbackSetup;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EnvironmentFactory;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use webignition\ObjectReflector\ObjectReflector;

class SendCallbackHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private SendCallbackHandler $handler;
    private CallbackRepository $callbackRepository;
    private CallbackStateMutator $stateMutator;
    private CallbackInterface $callback;

    protected function setUp(): void
    {
        parent::setUp();

        $sendCallbackHandler = self::getContainer()->get(SendCallbackHandler::class);
        \assert($sendCallbackHandler instanceof SendCallbackHandler);
        $this->handler = $sendCallbackHandler;

        $callbackRepository = self::getContainer()->get(CallbackRepository::class);
        \assert($callbackRepository instanceof CallbackRepository);
        $this->callbackRepository = $callbackRepository;

        $stateMutator = self::getContainer()->get(CallbackStateMutator::class);
        \assert($stateMutator instanceof CallbackStateMutator);
        $this->stateMutator = $stateMutator;

        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withCallbackSetups([
                (new CallbackSetup())
                    ->withState(CallbackInterface::STATE_QUEUED),
            ])
        ;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $environment = $environmentFactory->create($environmentSetup);

        $callbacks = $environment->getCallbacks();
        self::assertCount(1, $callbacks);

        $callback = $callbacks[0];
        self::assertInstanceOf(CallbackInterface::class, $callback);

        $this->callback = $callback;
    }

    /**
     * @dataProvider invokeSuccessDataProvider
     */
    public function testInvokeSuccess(
        ClientExceptionInterface | ResponseInterface $sendCallbackResultContext,
        string $expectedCallbackState,
    ): void {
        $expectedSentCallback = clone $this->callback;
        $expectedSentCallback->setState(CallbackInterface::STATE_SENDING);

        $sendCallbackResult = new SendCallbackResult($this->callback, $sendCallbackResultContext);

        $sender = (new MockCallbackSender())
            ->withSendCall($expectedSentCallback, $sendCallbackResult)
            ->getMock()
            ;

        $responseHandler = (new MockCallbackResponseHandler())
            ->withoutHandleCall()
            ->getMock()
        ;

        $this->doInvoke($sender, $responseHandler, $expectedCallbackState);
    }

    /**
     * @return array<mixed>
     */
    public function invokeSuccessDataProvider(): array
    {
        return [
            'success' => [
                'sendCallbackResultContext' => new Response(200),
                'expectedCallbackState' => CallbackInterface::STATE_COMPLETE,
            ],
        ];
    }

    /**
     * @dataProvider invokeFailureDataProvider
     */
    public function testInvokeFailure(
        ClientExceptionInterface | ResponseInterface $sendCallbackResultContext,
        string $expectedCallbackState,
    ): void {
        $expectedSentCallback = clone $this->callback;
        $expectedSentCallback->setState(CallbackInterface::STATE_SENDING);

        $sendCallbackResult = new SendCallbackResult($this->callback, $sendCallbackResultContext);

        $sender = (new MockCallbackSender())
            ->withSendCall($expectedSentCallback, $sendCallbackResult)
            ->getMock()
        ;

        $responseHandler = (new MockCallbackResponseHandler())
            ->withHandleCall($this->callback, $sendCallbackResultContext)
            ->getMock()
        ;

        $this->doInvoke($sender, $responseHandler, $expectedCallbackState);
    }

    /**
     * @return array<mixed>
     */
    public function invokeFailureDataProvider(): array
    {
        return [
            'HTTP 400' => [
                'sendCallbackResultContext' => new Response(400),
                'expectedCallbackState' => CallbackInterface::STATE_SENDING,
            ],
            'Guzzle ConnectException' => [
                'sendCallbackResultContext' => \Mockery::mock(ConnectException::class),
                'expectedCallbackState' => CallbackInterface::STATE_SENDING,
            ],
        ];
    }

    private function doInvoke(
        CallbackSender $sender,
        CallbackResponseHandler $responseHandler,
        string $expectedState
    ): void {
        $this->setCallbackSender($sender);
        $this->setCallbackResponseHandler($responseHandler);

        $message = new SendCallbackMessage((int) $this->callback->getId());

        self::assertSame(CallbackInterface::STATE_QUEUED, $this->callback->getState());

        ($this->handler)($message);

        $callback = $this->callbackRepository->find($this->callback->getId());
        self::assertInstanceOf(CallbackInterface::class, $callback);
        self::assertSame($expectedState, $this->callback->getState());
    }

    private function setCallbackSender(CallbackSender $callbackSender): void
    {
        ObjectReflector::setProperty($this->handler, $this->handler::class, 'sender', $callbackSender);
    }

    private function setCallbackResponseHandler(CallbackResponseHandler $responseHandler): void
    {
        ObjectReflector::setProperty(
            $this->handler,
            $this->handler::class,
            'callbackResponseHandler',
            $responseHandler
        );
    }
}
