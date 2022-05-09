<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\SendCallbackMessage;
use App\MessageHandler\SendCallbackHandler;
use App\Repository\WorkerEventRepository;
use App\Tests\Mock\Services\MockCallbackSender;
use App\Tests\Mock\Services\MockCallbackStateMutator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SendCallbackHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testInvokeCallbackNotExists(): void
    {
        $workerEventId = 0;
        $message = new SendCallbackMessage($workerEventId);

        $repository = \Mockery::mock(WorkerEventRepository::class);
        $repository
            ->shouldReceive('find')
            ->with($workerEventId)
            ->andReturnNull()
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

        $handler = new SendCallbackHandler($repository, $sender, $stateMutator);

        ($handler)($message);
    }
}
