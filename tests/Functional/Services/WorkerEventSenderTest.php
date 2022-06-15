<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventType;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Repository\JobRepository;
use App\Services\WorkerEventSender;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class WorkerEventSenderTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private WorkerEventSender $sender;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $sender = self::getContainer()->get(WorkerEventSender::class);
        \assert($sender instanceof WorkerEventSender);
        $this->sender = $sender;

        $mockHandler = self::getContainer()->get('app.tests.services.guzzle.handler.queuing');
        \assert($mockHandler instanceof MockHandler);
        $this->mockHandler = $mockHandler;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }
    }

    public function testSendSuccess(): void
    {
        $workerEvent = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            WorkerEventType::JOB_STARTED,
            'non-empty reference',
            []
        );
        $this->mockHandler->append(new Response(200));
        $this->createJob();

        try {
            $this->sender->send($workerEvent);
            $this->expectNotToPerformAssertions();
        } catch (NonSuccessfulHttpResponseException | ClientExceptionInterface $e) {
            $this->fail($e::class);
        }
    }

    /**
     * @dataProvider sendNonSuccessfulResponseDataProvider
     */
    public function testSendNonSuccessfulResponse(ResponseInterface $response): void
    {
        $workerEvent = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            WorkerEventType::JOB_STARTED,
            'non-empty reference',
            []
        );
        $this->mockHandler->append($response);
        $this->createJob();

        $this->expectExceptionObject(new NonSuccessfulHttpResponseException($workerEvent, $response));

        $this->sender->send($workerEvent);
    }

    /**
     * @return array<mixed>
     */
    public function sendNonSuccessfulResponseDataProvider(): array
    {
        $dataSets = [];

        for ($statusCode = 300; $statusCode < 600; ++$statusCode) {
            $dataSets[(string) $statusCode] = [
                'response' => new Response($statusCode),
            ];
        }

        return $dataSets;
    }

    /**
     * @dataProvider sendClientExceptionDataProvider
     */
    public function testSendClientException(\Exception $exception): void
    {
        $workerEvent = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            WorkerEventType::JOB_STARTED,
            'non-empty reference',
            []
        );
        $this->mockHandler->append($exception);
        $this->createJob();

        $this->expectExceptionObject($exception);

        $this->sender->send($workerEvent);
    }

    /**
     * @return array<mixed>
     */
    public function sendClientExceptionDataProvider(): array
    {
        return [
            'Guzzle ConnectException' => [
                'exception' => \Mockery::mock(ConnectException::class),
            ],
        ];
    }

    private function createJob(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add(new Job('label content', 'http://example.com/events', 10, ['test.yml']));
    }
}
