<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Repository\JobRepository;
use App\Repository\WorkerEventReferenceRepository;
use App\Repository\WorkerEventRepository;
use App\Services\WorkerEventSender;
use App\Tests\AbstractBaseFunctionalTestCase;
use App\Tests\Services\EntityRemover;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class WorkerEventSenderTest extends AbstractBaseFunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private WorkerEventSender $sender;
    private MockHandler $mockHandler;

    private WorkerEvent $event;

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
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(WorkerEventReference::class);
        }

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $jobRepository->add(new Job(
            'label content',
            'http://example.com/events',
            'results-token',
            10,
            ['test.yml']
        ));

        $this->event = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            new WorkerEventReference('non-empty label', 'non-empty reference'),
            []
        );

        $referenceRepository = self::getContainer()->get(WorkerEventReferenceRepository::class);
        \assert($referenceRepository instanceof WorkerEventReferenceRepository);
        $eventReference = $this->event->reference;
        \assert($eventReference instanceof WorkerEventReference);
        $referenceRepository->add($eventReference);

        $eventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($eventRepository instanceof WorkerEventRepository);
        $eventRepository->add($this->event);
    }

    public function testSendSuccess(): void
    {
        $this->mockHandler->append(new Response(200));

        try {
            $this->sender->send($this->event);
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
        $this->mockHandler->append($response);
        $this->expectExceptionObject(new NonSuccessfulHttpResponseException($this->event, $response));

        $this->sender->send($this->event);
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
        $this->mockHandler->append($exception);
        $this->expectExceptionObject($exception);

        $this->sender->send($this->event);
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
}
