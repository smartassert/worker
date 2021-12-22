<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Entity\Callback\CallbackInterface;
use App\Message\SendCallbackMessage;
use App\MessageHandler\SendCallbackHandler;
use App\Repository\CallbackRepository;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Mock\Services\MockCallbackSender;
use App\Tests\Mock\Services\MockCallbackStateMutator;
use App\Tests\Model\CallbackSetup;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EnvironmentFactory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use webignition\ObjectReflector\ObjectReflector;

class SendCallbackHandlerTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private SendCallbackHandler $handler;
    private CallbackRepository $callbackRepository;
    private EnvironmentFactory $environmentFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $sendCallbackHandler = self::getContainer()->get(SendCallbackHandler::class);
        \assert($sendCallbackHandler instanceof SendCallbackHandler);
        $this->handler = $sendCallbackHandler;

        $callbackRepository = self::getContainer()->get(CallbackRepository::class);
        \assert($callbackRepository instanceof CallbackRepository);
        $this->callbackRepository = $callbackRepository;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;
    }

    public function testInvokeCallbackNotExists(): void
    {
        $callback = \Mockery::mock(CallbackInterface::class);
        $callback
            ->shouldReceive('getId')
            ->andReturn(0)
        ;

        $stateMutator = (new MockCallbackStateMutator())
            ->withoutSetSendingCall()
            ->getMock()
        ;

        $sender = (new MockCallbackSender())
            ->withoutSendCall()
            ->getMock()
        ;

        ObjectReflector::setProperty($this->handler, SendCallbackHandler::class, 'stateMutator', $stateMutator);
        ObjectReflector::setProperty($this->handler, SendCallbackHandler::class, 'sender', $sender);

        $message = new SendCallbackMessage((int) $callback->getId());

        ($this->handler)($message);
    }

    public function testInvokeCallbackExists(): void
    {
        $environmentSetup = (new EnvironmentSetup())
            ->withJobSetup(new JobSetup())
            ->withCallbackSetups([
                new CallbackSetup(),
            ])
        ;

        $environment = $this->environmentFactory->create($environmentSetup);

        $callbacks = $environment->getCallbacks();
        self::assertCount(1, $callbacks);

        $callback = $callbacks[0];
        self::assertInstanceOf(CallbackInterface::class, $callback);

        $mockSender = new MockCallbackSender();

        $expectedSentCallback = $this->callbackRepository->find($callback->getId());

        if ($expectedSentCallback instanceof CallbackInterface) {
            $expectedSentCallback->setState(CallbackInterface::STATE_SENDING);
            $mockSender = $mockSender->withSendCall($expectedSentCallback);
        }

        ObjectReflector::setProperty($this->handler, SendCallbackHandler::class, 'sender', $mockSender->getMock());

        $message = new SendCallbackMessage((int) $callback->getId());

        ($this->handler)($message);
    }
}
