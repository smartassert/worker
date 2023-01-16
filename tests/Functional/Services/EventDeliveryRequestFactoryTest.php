<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Services\EventDeliveryRequestFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use webignition\ObjectReflector\ObjectReflector;

class EventDeliveryRequestFactoryTest extends AbstractBaseFunctionalTest
{
    private const JOB_LABEL = 'job label';

    private EventDeliveryRequestFactory $factory;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = self::getContainer()->get(EventDeliveryRequestFactory::class);
        \assert($factory instanceof EventDeliveryRequestFactory);
        $this->factory = $factory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        if ($environmentFactory instanceof EnvironmentFactory) {
            $environment = $environmentFactory->create(
                (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())->withLabel(self::JOB_LABEL)
                    )
            );

            $job = $environment->getJob();
            \assert($job instanceof Job);

            $this->job = $job;
        }
    }

    /**
     * @dataProvider createDataProvider
     *
     * @param array<mixed> $expectedRequestPayload
     */
    public function testCreate(WorkerEvent $event, array $expectedRequestPayload): void
    {
        $request = $this->factory->create($this->job, $event);

        self::assertSame('POST', $request->getMethod());
        self::assertSame($this->job->eventDeliveryUrl, (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('content-type'));
        self::assertEquals($expectedRequestPayload, json_decode($request->getBody()->getContents(), true));
    }

    /**
     * @return array<mixed>
     */
    public function createDataProvider(): array
    {
        $jobStartedWorkerEvent = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            new WorkerEventReference(self::JOB_LABEL, 'job/started reference'),
            [
                'tests' => ['test1.yml', 'test2.yml'],
            ]
        );

        ObjectReflector::setProperty($jobStartedWorkerEvent, $jobStartedWorkerEvent::class, 'id', 3);

        return [
            'job/started' => [
                'event' => $jobStartedWorkerEvent,
                'expectedRequestPayload' => [
                    'header' => [
                        'sequence_number' => 3,
                        'type' => 'job/started',
                        'label' => 'job label',
                        'reference' => 'job/started reference',
                    ],
                    'body' => [
                        'tests' => ['test1.yml', 'test2.yml'],
                    ]
                ],
            ],
        ];
    }
}
