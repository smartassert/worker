<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Job;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Message\SendCallbackMessage;
use App\MessageHandler\SendCallbackHandler;
use App\Repository\CallbackRepository;
use App\Services\CallbackSender;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockCallbackSender;
use App\Tests\Model\CallbackSetup;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\ObjectReflector\ObjectReflector;

class SendCallbackHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private SendCallbackHandler $handler;
    private CallbackRepository $callbackRepository;
    private CallbackEntity $callback;

    protected function setUp(): void
    {
        parent::setUp();

        $sendCallbackHandler = self::getContainer()->get(SendCallbackHandler::class);
        \assert($sendCallbackHandler instanceof SendCallbackHandler);
        $this->handler = $sendCallbackHandler;

        $callbackRepository = self::getContainer()->get(CallbackRepository::class);
        \assert($callbackRepository instanceof CallbackRepository);
        $this->callbackRepository = $callbackRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }

        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withCallbackSetups([
                (new CallbackSetup())
                    ->withState(CallbackEntity::STATE_QUEUED),
            ])
        ;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $environment = $environmentFactory->create($environmentSetup);

        $callbacks = $environment->getCallbacks();
        self::assertCount(1, $callbacks);

        $callback = $callbacks[0];
        self::assertInstanceOf(CallbackEntity::class, $callback);

        $this->callback = $callback;
    }

    public function testInvokeSuccess(): void
    {
        $expectedSentCallback = clone $this->callback;
        $expectedSentCallback->setState(CallbackEntity::STATE_SENDING);

        $this->setCallbackSender((new MockCallbackSender())
            ->withSendCall($expectedSentCallback)
            ->getMock());

        $message = new SendCallbackMessage((int) $this->callback->getId());

        self::assertSame(CallbackEntity::STATE_QUEUED, $this->callback->getState());

        ($this->handler)($message);

        $callback = $this->callbackRepository->find($this->callback->getId());
        self::assertInstanceOf(CallbackEntity::class, $callback);
        self::assertSame(CallbackEntity::STATE_COMPLETE, $this->callback->getState());
    }

    /**
     * @dataProvider invokeFailureDataProvider
     */
    public function testInvokeFailure(\Exception $callbackSenderException, string $expectedCallbackState): void
    {
        $expectedSentCallback = clone $this->callback;
        $expectedSentCallback->setState(CallbackEntity::STATE_SENDING);

        $this->setCallbackSender((new MockCallbackSender())
            ->withSendCall($expectedSentCallback, $callbackSenderException)
            ->getMock());

        $message = new SendCallbackMessage((int) $this->callback->getId());

        self::assertSame(CallbackEntity::STATE_QUEUED, $this->callback->getState());

        try {
            ($this->handler)($message);
            $this->fail($callbackSenderException::class . ' not thrown');
        } catch (\Throwable $exception) {
            self::assertSame($callbackSenderException, $exception);
        }

        $callback = $this->callbackRepository->find($this->callback->getId());
        self::assertInstanceOf(CallbackEntity::class, $callback);
        self::assertSame($expectedCallbackState, $this->callback->getState());
    }

    /**
     * @return array<mixed>
     */
    public function invokeFailureDataProvider(): array
    {
        return [
            'HTTP 400' => [
                'callbackSenderException' => new NonSuccessfulHttpResponseException(
                    new CallbackEntity(),
                    new Response(400)
                ),
                'expectedCallbackState' => CallbackEntity::STATE_SENDING,
            ],
            'Guzzle ConnectException' => [
                'callbackSenderException' => \Mockery::mock(ConnectException::class),
                'expectedCallbackState' => CallbackEntity::STATE_SENDING,
            ],
        ];
    }

    private function setCallbackSender(CallbackSender $callbackSender): void
    {
        ObjectReflector::setProperty($this->handler, $this->handler::class, 'sender', $callbackSender);
    }
}
