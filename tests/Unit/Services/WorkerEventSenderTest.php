<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use App\Exception\JobNotFoundException;
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
        $exception = new JobNotFoundException();
        $jobRepository = (new MockJobRepository())
            ->withGetCall($exception)
            ->getMock()
        ;

        $sender = new WorkerEventSender($httpClient, $jobRepository);

        self::expectExceptionObject($exception);

        $sender->send(new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventType::JOB_STARTED,
            'non-empty reference',
            []
        ));
    }
}
