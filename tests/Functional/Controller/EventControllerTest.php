<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;

class EventControllerTest extends AbstractBaseFunctionalTest
{
    private ClientRequestSender $clientRequestSender;
    private EnvironmentFactory $environmentFactory;
    private JsonResponseAsserter $jsonResponseAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $clientRequestSender = self::getContainer()->get(ClientRequestSender::class);
        \assert($clientRequestSender instanceof ClientRequestSender);
        $this->clientRequestSender = $clientRequestSender;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $jsonResponseAsserter = self::getContainer()->get(JsonResponseAsserter::class);
        \assert($jsonResponseAsserter instanceof JsonResponseAsserter);
        $this->jsonResponseAsserter = $jsonResponseAsserter;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Job::class);
        }
    }

    public function testGetNoJob(): void
    {
        $response = $this->clientRequestSender->getEvent(123);

        $this->jsonResponseAsserter->assertJsonResponse(400, [], $response);
    }

    public function testGetEventNotFound(): void
    {
        $this->environmentFactory->create(
            (new EnvironmentSetup())->withJobSetup(
                new JobSetup()
            )
        );

        $response = $this->clientRequestSender->getEvent(123);

        $this->jsonResponseAsserter->assertJsonResponse(404, [], $response);
    }

    public function testGetSuccess(): void
    {
        $eventLabel = md5((string) rand());
        $eventPayload = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];
        $eventReference = md5((string) rand());

        $environment = $this->environmentFactory->create(
            (new EnvironmentSetup())
                ->withJobSetup(
                    new JobSetup()
                )
                ->withWorkerEventSetups([
                    (new WorkerEventSetup())
                        ->withLabel($eventLabel)
                        ->withPayload($eventPayload)
                        ->withReference($eventReference)
                        ->withScope(WorkerEventScope::JOB)
                        ->withOutcome(WorkerEventOutcome::COMPLETED)
                ])
        );

        $job = $environment->getJob();
        \assert($job instanceof Job);

        $event = $environment->getWorkerEvents()[0];

        $response = $this->clientRequestSender->getEvent((int) $event->getId());

        $this->jsonResponseAsserter->assertJsonResponse(
            200,
            [
                'job' => $job->getLabel(),
                'label' => $eventLabel,
                'payload' => $eventPayload,
                'reference' => $eventReference,
                'sequence_number' => $event->getId(),
                'state' => WorkerEventState::AWAITING->value,
                'type' => 'job/completed',
            ],
            $response
        );
    }
}
