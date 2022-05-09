<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\WorkerEvent;
use App\Services\WorkerEventSender;
use App\Tests\Mock\Repository\MockJobRepository;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

class CallbackSenderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testSendNoJob(): void
    {
        $httpClient = \Mockery::mock(ClientInterface::class);

        $jobRepository = (new MockJobRepository())
            ->withGetCall(null)
            ->getMock()
        ;

        $callbackSender = new WorkerEventSender($httpClient, $jobRepository);
        $callbackSender->send(WorkerEvent::create(
            WorkerEvent::TYPE_JOB_STARTED,
            'non-empty reference',
            []
        ));
    }
}
