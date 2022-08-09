<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Exception\JobNotFoundException;
use App\Services\EventDeliveryRequestFactory;
use App\Services\WorkerEventSender;
use App\Services\WorkerEventSerializer;
use App\Tests\Mock\Repository\MockJobRepository;
use GuzzleHttp\Psr7\HttpFactory;
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

        $httpFactory = new HttpFactory();
        $requestFactory = new EventDeliveryRequestFactory($httpFactory, $httpFactory, new WorkerEventSerializer());

        $sender = new WorkerEventSender($httpClient, $jobRepository, $requestFactory);

        self::expectExceptionObject($exception);

        $sender->send(new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            new WorkerEventReference('non-empty label', 'non-empty reference'),
            []
        ));
    }
}
