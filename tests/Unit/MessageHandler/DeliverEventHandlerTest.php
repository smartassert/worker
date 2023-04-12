<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\DeliverEventMessage;
use App\MessageHandler\DeliverEventHandler;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventSender;
use App\Services\WorkerEventStateMutator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DeliverEventHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testInvokeWorkerEventDoesNotExist(): void
    {
        $workerEventId = 0;
        $message = new DeliverEventMessage($workerEventId);

        $repository = \Mockery::mock(WorkerEventRepository::class);
        $repository
            ->shouldReceive('find')
            ->with($workerEventId)
            ->andReturnNull()
        ;

        $stateMutator = \Mockery::mock(WorkerEventStateMutator::class);
        $stateMutator->shouldNotReceive('setSending');
        $stateMutator->shouldNotReceive('setComplete');

        $handler = new DeliverEventHandler(
            $repository,
            \Mockery::mock(WorkerEventSender::class),
            $stateMutator
        );

        ($handler)($message);
    }
}
