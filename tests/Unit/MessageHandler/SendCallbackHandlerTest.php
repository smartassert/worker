<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\SendCallbackMessage;
use App\MessageHandler\SendCallbackHandler;
use App\Tests\Mock\Repository\MockCallbackRepository;
use App\Tests\Mock\Services\MockCallbackResponseHandler;
use App\Tests\Mock\Services\MockCallbackSender;
use App\Tests\Mock\Services\MockCallbackStateMutator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SendCallbackHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testInvokeCallbackNotExists(): void
    {
        $callbackId = 0;
        $message = new SendCallbackMessage($callbackId);

        $repository = (new MockCallbackRepository())
            ->withFindCall($callbackId, null)
            ->getMock()
        ;

        $sender = (new MockCallbackSender())
            ->withoutSendCall()
            ->getMock()
        ;

        $stateMutator = (new MockCallbackStateMutator())
            ->withoutSetSendingCall()
            ->withoutSetCompleteCall()
            ->getMock()
        ;

        $responseHandler = (new MockCallbackResponseHandler())
            ->withoutHandleCall()
            ->getMock()
        ;

        $handler = new SendCallbackHandler($repository, $sender, $stateMutator, $responseHandler);

        ($handler)($message);
    }
}
