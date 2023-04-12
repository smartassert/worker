<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Exception\JobNotFoundException;
use App\Repository\JobRepository;
use App\Services\WorkerEventSender;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SmartAssert\ResultsClient\Client;

class WorkerEventSenderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testSendNoJob(): void
    {
        $exception = new JobNotFoundException();

        $jobRepository = \Mockery::mock(JobRepository::class);
        $jobRepository
            ->shouldReceive('get')
            ->andThrow($exception)
        ;

        $resultsClient = \Mockery::mock(Client::class);

        $sender = new WorkerEventSender($jobRepository, $resultsClient);

        self::expectExceptionObject($exception);

        $sender->send(new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            new WorkerEventReference('non-empty label', 'non-empty reference'),
            []
        ));
    }
}
