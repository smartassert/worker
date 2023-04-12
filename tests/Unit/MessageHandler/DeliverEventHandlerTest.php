<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\DeliverEventMessage;
use App\MessageHandler\DeliverEventHandler;
use App\Repository\JobRepository;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventStateMutator;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SmartAssert\ResultsClient\Client as ResultsClient;

class DeliverEventHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testInvokeWorkerEventDoesNotExist(): void
    {
        $workerEventId = 0;
        $message = new DeliverEventMessage($workerEventId);

        $workerEventRepository = \Mockery::mock(WorkerEventRepository::class);
        $workerEventRepository
            ->shouldReceive('find')
            ->with($workerEventId)
            ->andReturnNull()
        ;

        $stateMutator = \Mockery::mock(WorkerEventStateMutator::class);
        $stateMutator->shouldNotReceive('setSending');
        $stateMutator->shouldNotReceive('setComplete');

        $handler = new DeliverEventHandler(
            \Mockery::mock(JobRepository::class),
            $workerEventRepository,
            $stateMutator,
            \Mockery::mock(ResultsClient::class)
        );

        ($handler)($message);
    }
}
