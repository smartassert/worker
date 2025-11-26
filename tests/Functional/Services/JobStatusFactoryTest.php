<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services;

use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Enum\TestState;
use App\Repository\WorkerEventRepository;
use App\Services\JobStatusFactory;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Model\WorkerEventSetup;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class JobStatusFactoryTest extends WebTestCase
{
    private JobStatusFactory $jobStatusFactory;
    private EnvironmentFactory $environmentFactory;
    private WorkerEventRepository $workerEventRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $jobStatusFactory = self::getContainer()->get(JobStatusFactory::class);
        \assert($jobStatusFactory instanceof JobStatusFactory);
        $this->jobStatusFactory = $jobStatusFactory;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $this->workerEventRepository = $workerEventRepository;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Job::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
        }
    }

    /**
     * @dataProvider createWithoutEventIdsDataProvider
     *
     * @param array<mixed> $expectedSerializedJobStatus
     */
    public function testCreateWithoutEventIds(
        EnvironmentSetup $environmentSetup,
        array $expectedSerializedJobStatus
    ): void {
        $environment = $this->environmentFactory->create($environmentSetup);
        $job = $environment->getJob();
        self::assertInstanceOf(Job::class, $job);

        $jobStatus = $this->jobStatusFactory->create($job);

        self::assertEquals($expectedSerializedJobStatus, $jobStatus->jsonSerialize());
    }

    /**
     * @return array<mixed>
     */
    public static function createWithoutEventIdsDataProvider(): array
    {
        return [
            'no sources, no tests' => [
                'environmentSetup' => (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())
                            ->withLabel('no sources, no tests label')
                            ->withMaximumDurationInSeconds(1)
                            ->withTestPaths([
                                'Test/no-sources-no-tests.yml',
                            ])
                    ),
                'expectedSerializedJobStatus' => [
                    'label' => 'no sources, no tests label',
                    'maximum_duration_in_seconds' => 1,
                    'test_paths' => [
                        'Test/no-sources-no-tests.yml',
                    ],
                    'reference' => md5('no sources, no tests label'),
                    'sources' => [],
                    'tests' => [],
                    'references' => [
                        [
                            'label' => 'Test/no-sources-no-tests.yml',
                            'reference' => md5('no sources, no tests labelTest/no-sources-no-tests.yml'),
                        ],
                    ],
                    'event_ids' => [],
                ],
            ],
            'has sources, has tests created not in sequential position order' => [
                'environmentSetup' => (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())
                            ->withLabel('has sources, has tests label')
                            ->withMaximumDurationInSeconds(2)
                            ->withTestPaths([
                                'Test/has-sources-has-tests1.yml',
                                'Test/has-sources-has-tests2.yml',
                            ])
                    )
                    ->withSourceSetups([
                        (new SourceSetup())
                            ->withPath('Test/has-sources-has-tests1.yml'),
                        (new SourceSetup())
                            ->withPath('Test/has-sources-has-tests2.yml'),
                        (new SourceSetup())
                            ->withPath('Page/referenced-page.yml'),
                    ])
                    ->withTestSetups([
                        (new TestSetup())
                            ->withSource('Test/has-sources-has-tests2.yml')
                            ->withBrowser('firefox')
                            ->withUrl('http://example.com/test-bar')
                            ->withStepNames(['bar-step-3', 'bar-step-4'])
                            ->withTarget('GeneratedFirefoxTest.php')
                            ->withState(TestState::RUNNING)
                            ->withPosition(2),
                        (new TestSetup())
                            ->withSource('Test/has-sources-has-tests1.yml')
                            ->withBrowser('chrome')
                            ->withUrl('http://example.com/test-foo')
                            ->withStepNames(['foo-step-one', 'foo-step-2'])
                            ->withTarget('GeneratedChromeTest.php')
                            ->withState(TestState::COMPLETE)
                            ->withPosition(1),
                        (new TestSetup())
                            ->withSource('Test/has-sources-has-tests3.yml')
                            ->withBrowser('firefox')
                            ->withUrl('http://example.com/test-bar')
                            ->withStepNames(['bar-step-3', 'bar-step-4'])
                            ->withTarget('GeneratedFirefoxTest.php')
                            ->withState(TestState::RUNNING)
                            ->withPosition(3),
                    ]),
                'expectedSerializedJobStatus' => [
                    'label' => 'has sources, has tests label',
                    'maximum_duration_in_seconds' => 2,
                    'test_paths' => [
                        'Test/has-sources-has-tests1.yml',
                        'Test/has-sources-has-tests2.yml',
                    ],
                    'reference' => md5('has sources, has tests label'),
                    'sources' => [
                        'Test/has-sources-has-tests1.yml',
                        'Test/has-sources-has-tests2.yml',
                        'Page/referenced-page.yml',
                    ],
                    'tests' => [
                        [
                            'browser' => 'chrome',
                            'url' => 'http://example.com/test-foo',
                            'source' => 'Test/has-sources-has-tests1.yml',
                            'target' => 'GeneratedChromeTest.php',
                            'step_names' => [
                                'foo-step-one',
                                'foo-step-2',
                            ],
                            'state' => 'complete',
                            'position' => 1,
                        ],
                        [
                            'browser' => 'firefox',
                            'url' => 'http://example.com/test-bar',
                            'source' => 'Test/has-sources-has-tests2.yml',
                            'target' => 'GeneratedFirefoxTest.php',
                            'step_names' => [
                                'bar-step-3',
                                'bar-step-4',
                            ],
                            'state' => 'running',
                            'position' => 2,
                        ],
                        [
                            'browser' => 'firefox',
                            'url' => 'http://example.com/test-bar',
                            'source' => 'Test/has-sources-has-tests3.yml',
                            'target' => 'GeneratedFirefoxTest.php',
                            'step_names' => [
                                'bar-step-3',
                                'bar-step-4',
                            ],
                            'state' => 'running',
                            'position' => 3,
                        ],
                    ],
                    'references' => [
                        [
                            'label' => 'Test/has-sources-has-tests1.yml',
                            'reference' => md5('has sources, has tests labelTest/has-sources-has-tests1.yml'),
                        ],
                        [
                            'label' => 'Test/has-sources-has-tests2.yml',
                            'reference' => md5('has sources, has tests labelTest/has-sources-has-tests2.yml'),
                        ],
                    ],
                    'event_ids' => [],
                ],
            ],
        ];
    }

    public function testCreateHasEventIds(): void
    {
        $environment = $this->environmentFactory->create(
            (new EnvironmentSetup())
                ->withJobSetup(
                    (new JobSetup())
                        ->withTestPaths([
                            'Test/test.yml',
                        ])
                )
                ->withSourceSetups([])
                ->withTestSetups([])
                ->withWorkerEventSetups([
                    new WorkerEventSetup(),
                    new WorkerEventSetup(),
                    new WorkerEventSetup(),
                ])
        );

        $job = $environment->getJob();
        self::assertInstanceOf(Job::class, $job);

        self::assertEquals(
            [
                'label' => $job->getLabel(),
                'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                'test_paths' => $job->getTestPaths(),
                'reference' => md5($job->getLabel()),
                'sources' => [],
                'tests' => [],
                'references' => [
                    [
                        'label' => 'Test/test.yml',
                        'reference' => md5($job->getLabel() . 'Test/test.yml'),
                    ],
                ],
                'event_ids' => $this->workerEventRepository->findAllIds(),
            ],
            $this->jobStatusFactory->create($job)->jsonSerialize()
        );
    }
}
