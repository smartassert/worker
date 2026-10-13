<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventState;
use App\Repository\WorkerEventRepository;
use App\Request\CreateJobRequest;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\CreateJobSourceFactory;
use App\Tests\Services\EntityRemover;
use PHPUnit\Framework\Attributes\DataProvider;
use SmartAssert\CallbackReceiverLogReader\Parser;
use Symfony\Component\Process\Process;
use webignition\WaitFor\WaitFor;

class FailedWorkerEventTest extends AbstractIntegrationTestCase
{
    private ClientRequestSender $clientRequestSender;
    private JsonResponseAsserter $jsonResponseAsserter;
    private CreateJobSourceFactory $createJobSourceFactory;
    private WorkerEventRepository $workerEventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $clientRequestSender = self::getContainer()->get(ClientRequestSender::class);
        \assert($clientRequestSender instanceof ClientRequestSender);
        $this->clientRequestSender = $clientRequestSender;

        $jsonResponseAsserter = self::getContainer()->get(JsonResponseAsserter::class);
        \assert($jsonResponseAsserter instanceof JsonResponseAsserter);
        $this->jsonResponseAsserter = $jsonResponseAsserter;

        $createJobSourceFactory = self::getContainer()->get(CreateJobSourceFactory::class);
        \assert($createJobSourceFactory instanceof CreateJobSourceFactory);
        $this->createJobSourceFactory = $createJobSourceFactory;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);
        $entityRemover->removeAll();
    }

    public function testBadEventUrlResultsInFailedWorkerEvents(): void
    {
        $jobLabel = md5((string) rand());

        $jobMaximumDurationInSeconds = 60;

        $jobStatusResponse = $this->clientRequestSender->getJobStatus();
        $this->jsonResponseAsserter->assertJsonResponse(400, [], $jobStatusResponse);

        $createResponse = self::makeCreateJobRequest([
            CreateJobRequest::KEY_LABEL => $jobLabel,
            CreateJobRequest::KEY_EVENT_NOTIFY_URL => 'http://httpbin/status/418',
            CreateJobRequest::KEY_EVENT_NOTIFY_TOKEN => 'event-notify-token',
            CreateJobRequest::KEY_MAXIMUM_DURATION => $jobMaximumDurationInSeconds,
            CreateJobRequest::KEY_SOURCE => $this->createJobSourceFactory->create(
                [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
                [
                    'Test/chrome-open-index-compilation-failure.yml',
                ],
            ),
            CreateJobRequest::STATE_NOTIFY_URL => 'http://callback-receiver:8080',
        ]);

        self::assertSame(200, $createResponse->getStatusCode());
        self::assertSame('application/json', $createResponse->getHeaderLine('content-type'));

        new WaitFor()->waitFor(
            $jobMaximumDurationInSeconds,
            function () {
                return self::isApplicationComplete();
            },
            'application to finish.'
        );

        sleep(2);

        $workerEventCount = $this->workerEventRepository->count();
        $failedWorkerEventCount = $this->workerEventRepository->count([
            'state' => WorkerEventState::FAILED,
        ]);

        $jobData = self::fetchJob();

        self::assertArrayHasKey('event_ids', $jobData);
        $eventIds = $jobData['event_ids'];
        self::assertIsArray($eventIds);
        self::assertCount($workerEventCount, $eventIds);
        self::assertCount($failedWorkerEventCount, $eventIds);
    }

    private static function isApplicationComplete(): bool
    {
        $state = self::fetchApplicationState();

        return true === self::isComponentEndState($state, 'application')
            && true === self::isComponentEndState($state, 'event_delivery');
    }

    /**
     * @param array<mixed>     $data
     * @param non-empty-string $key
     */
    private static function isComponentEndState(array $data, string $key): bool
    {
        $state = $data[$key] ?? [];
        $state = is_array($state) ? $state : [];

        $metaState = $state['meta_state'] ?? [];
        $metaState = is_array($metaState) ? $metaState : [];

        $metaStateEnded = $metaState['ended'] ?? false;

        return is_bool($metaStateEnded) && $metaStateEnded;
    }
}
