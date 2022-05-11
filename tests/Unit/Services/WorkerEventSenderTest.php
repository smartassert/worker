<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventType;
use App\Services\WorkerEventSender;
use App\Tests\Mock\Repository\MockJobRepository;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

class WorkerEventSenderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testSendNoJob(): void
    {
        $httpClient = \Mockery::mock(ClientInterface::class);

        $jobRepository = (new MockJobRepository())
            ->withGetCall(null)
            ->getMock()
        ;

        $sender = new WorkerEventSender($httpClient, $jobRepository);
        $sender->send(WorkerEvent::create(
            WorkerEventType::JOB_STARTED,
            'non-empty reference',
            []
        ));
    }
}
