<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\JobController;
use App\Entity\Job;
use App\Entity\Source;
use App\Entity\Test;
use App\Entity\WorkerEvent;
use App\Event\JobStartedEvent;
use App\Repository\JobRepository;
use App\Repository\SourceRepository;
use App\Request\CreateJobRequest;
use App\Services\ErrorResponseFactory;
use App\Services\ReferenceFactory;
use App\Services\SourceFactory;
use App\Services\YamlSourceCollectionFactory;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\CreateJobSourceFactory;
use App\Tests\Services\EntityRemover;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\FixtureReader;
use App\Tests\Services\SourceFileInspector;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\YamlFile\Collection\Deserializer;

class JobControllerTest extends AbstractBaseFunctionalTest
{
    private JobRepository $jobRepository;
    private ClientRequestSender $clientRequestSender;
    private EnvironmentFactory $environmentFactory;
    private JsonResponseAsserter $jsonResponseAsserter;
    private SourceRepository $sourceRepository;
    private SourceFileInspector $sourceFileInspector;
    private FixtureReader $fixtureReader;
    private CreateJobSourceFactory $createJobSourceFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        $this->jobRepository = $jobRepository;

        $clientRequestSender = self::getContainer()->get(ClientRequestSender::class);
        \assert($clientRequestSender instanceof ClientRequestSender);
        $this->clientRequestSender = $clientRequestSender;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $jsonResponseAsserter = self::getContainer()->get(JsonResponseAsserter::class);
        \assert($jsonResponseAsserter instanceof JsonResponseAsserter);
        $this->jsonResponseAsserter = $jsonResponseAsserter;

        $sourceRepository = self::getContainer()->get(SourceRepository::class);
        \assert($sourceRepository instanceof SourceRepository);
        $this->sourceRepository = $sourceRepository;

        $sourceFileInspector = self::getContainer()->get(SourceFileInspector::class);
        \assert($sourceFileInspector instanceof SourceFileInspector);
        $this->sourceFileInspector = $sourceFileInspector;

        $fixtureReader = self::getContainer()->get(FixtureReader::class);
        \assert($fixtureReader instanceof FixtureReader);
        $this->fixtureReader = $fixtureReader;

        $createJobSourceFactory = self::getContainer()->get(CreateJobSourceFactory::class);
        \assert($createJobSourceFactory instanceof CreateJobSourceFactory);
        $this->createJobSourceFactory = $createJobSourceFactory;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(WorkerEvent::class);
            $entityRemover->removeForEntity(Source::class);
            $entityRemover->removeForEntity(Test::class);
            $entityRemover->removeForEntity(Job::class);
        }
    }

    /**
     * @!dataProvider createBadRequestMissingValuesDataProvider
     * @dataProvider createBadRequestInvalidSourceDataProvider
     *
     * @param array<mixed> $requestPayload
     * @param array<mixed> $expectedResponseData
     */
    public function testCreateBadRequest(array $requestPayload, array $expectedResponseData): void
    {
        self::assertNull($this->jobRepository->get());

        $response = $this->clientRequestSender->create($requestPayload);
        $this->jsonResponseAsserter->assertJsonResponse(400, $expectedResponseData, $response);

        self::assertNull($this->jobRepository->get());
    }

    /**
     * @return array<mixed>
     */
    public function createBadRequestMissingValuesDataProvider(): array
    {
        $label = 'label value';
        $eventDeliveryUrl = 'https://example.com/events';
        $maximumDurationInSeconds = 600;
        $nonEmptySource = 'non-empty source';

        $nonEmptyPayload = [
            CreateJobRequest::KEY_LABEL => $label,
            CreateJobRequest::KEY_EVENT_DELIVERY_URL => $eventDeliveryUrl,
            CreateJobRequest::KEY_MAXIMUM_DURATION => $maximumDurationInSeconds,
            CreateJobRequest::KEY_SOURCE => $nonEmptySource,
        ];

        return [
            'missing values: label missing' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_LABEL => null,
                ]),
                'expectedResponseData' => [
                    'error_state' => 'label/missing',
                ],
            ],
            'missing values: label empty' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_LABEL => '',
                ]),
                'expectedResponseData' => [
                    'error_state' => 'label/missing',
                ],
            ],
            'missing values: event_delivery_url missing' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_EVENT_DELIVERY_URL => null,
                ]),
                'expectedResponseData' => [
                    'error_state' => 'event_delivery_url/missing',
                ],
            ],
            'missing values: event_delivery_url empty' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_EVENT_DELIVERY_URL => '',
                ]),
                'expectedResponseData' => [
                    'error_state' => 'event_delivery_url/missing',
                ],
            ],
            'missing values: maximum_duration_in_seconds missing' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_MAXIMUM_DURATION => null,
                ]),
                'expectedResponseData' => [
                    'error_state' => 'maximum_duration_in_seconds/missing',
                ],
            ],
            'missing values: maximum_duration_in_seconds empty' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_MAXIMUM_DURATION => '',
                ]),
                'expectedResponseData' => [
                    'error_state' => 'maximum_duration_in_seconds/missing',
                ],
            ],
            'missing values: maximum_duration_in_seconds not an integer' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_MAXIMUM_DURATION => 'string',
                ]),
                'expectedResponseData' => [
                    'error_state' => 'maximum_duration_in_seconds/missing',
                ],
            ],
            'missing values: source missing' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_SOURCE => null
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/missing',
                ],
            ],
            'missing values: source empty' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_SOURCE => ''
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/missing',
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function createBadRequestInvalidSourceDataProvider(): array
    {
        $nonSourcePayload = [
            CreateJobRequest::KEY_LABEL => 'label value',
            CreateJobRequest::KEY_EVENT_DELIVERY_URL => 'https://example.com/events',
            CreateJobRequest::KEY_MAXIMUM_DURATION => 600,
        ];

        return [
            'invalid source: metadata not valid yaml' => [
                'requestPayload' => array_merge($nonSourcePayload, [
                    CreateJobRequest::KEY_SOURCE => <<< 'EOT'
                    ---
                      invalid
                    yaml
                    ...
                    EOT
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/metadata/invalid',
                    'payload' => [
                        'file_hashes_content' => '  invalid' . "\n" . 'yaml',
                        'message' => 'Serialized source metadata cannot be decoded',
                        'previous_message' => 'Unable to parse at line 1 (near "  invalid").',
                    ],
                ],
            ],
            'invalid source: file hash not found' => [
                'requestPayload' => array_merge($nonSourcePayload, [
                    CreateJobRequest::KEY_SOURCE => <<< 'EOT'
                    ---
                    123:
                        - file.yaml
                    ...
                    ---
                    file1.yaml content
                    ...
                    EOT
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/metadata/invalid',
                    'payload' => [
                        'file_hashes_content' => '123:' . "\n" . '    - file.yaml',
                        'message' => 'Serialized source metadata cannot be decoded',
                    ],
                ],
            ],
            'invalid source: metadata incomplete' => [
                'requestPayload' => array_merge($nonSourcePayload, [
                    CreateJobRequest::KEY_SOURCE => <<< 'EOT'
                    ---
                    hash_content:
                        - file.yaml
                    ...
                    ---
                    file1.yaml content
                    ...
                    EOT
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/metadata/incomplete',
                    'payload' => [
                        'hash' => '272c8402fa38edc52165379d6d3c356a',
                        'message' => 'Serialized source metadata is not complete',
                    ],
                ],
            ],
            'invalid source: invalid manifest: empty' => [
                'requestPayload' => array_merge($nonSourcePayload, [
                    CreateJobRequest::KEY_SOURCE => <<< 'EOT'
                    ---
                    d41d8cd98f00b204e9800998ecf8427e:
                        - manifest.yaml
                    ...
                    ---
                    ...
                    EOT
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/manifest/empty',
                ],
            ],
            'invalid source: invalid manifest: invalid yaml within manifest' => [
                'requestPayload' => array_merge($nonSourcePayload, [
                    CreateJobRequest::KEY_SOURCE => <<< 'EOT'
                    ---
                    3dce4acdc7912a59eaeb7a4ebad24c44:
                        - manifest.yaml
                    ...
                    ---
                      invalid
                    yaml
                    ...
                    EOT
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/manifest/invalid',
                    'payload' => [
                        'message' => 'Unable to parse at line 1 (near "  invalid").',
                    ],
                ],
            ],
            'invalid source: missing manifest' => [
                'requestPayload' => array_merge($nonSourcePayload, [
                    CreateJobRequest::KEY_SOURCE => <<< 'EOT'
                    ---
                    158bb7a11c6230d913642ed45a3dffbe:
                        - file1.yaml
                    ...
                    ---
                    file1content
                    ...
                    EOT
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/manifest/missing',
                ],
            ],
            'invalid source: source file not present' => [
                'requestPayload' => array_merge($nonSourcePayload, [
                    CreateJobRequest::KEY_SOURCE => <<< 'EOT'
                    ---
                    eef1a102a86969433b2e102e378cc623:
                        - manifest.yaml
                    6f108c6f8b53deb2ab3f5ccc3865e2eb:
                        - Test/chrome-open-index.yml
                    ...
                    ---
                    - Test/chrome-open-index.yml
                    ...
                    EOT
                ]),
                'expectedResponseData' => [
                    'error_state' => 'source/test/missing',
                    'payload' => [
                        'path' => 'Test/chrome-open-index.yml',
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider createSuccessDataProvider
     *
     * @param string[]                    $manifestPaths
     * @param string[]                    $sourcePaths
     * @param array<string, array<mixed>> $expectedStoredSources
     */
    public function testCreateSuccess(
        array $manifestPaths,
        array $sourcePaths,
        array $expectedStoredSources,
    ): void {
        self::assertNull($this->jobRepository->get());

        $label = md5((string) rand());
        $eventDeliveryUrl = md5((string) rand());
        $maximumDuration = rand(1, 1000);

        $requestPayload = [
            CreateJobRequest::KEY_LABEL => $label,
            CreateJobRequest::KEY_EVENT_DELIVERY_URL => $eventDeliveryUrl,
            CreateJobRequest::KEY_MAXIMUM_DURATION => $maximumDuration,
            CreateJobRequest::KEY_SOURCE => $this->createJobSourceFactory->create($manifestPaths, $sourcePaths),
        ];

        $response = $this->clientRequestSender->create($requestPayload);
        $this->jsonResponseAsserter->assertJsonResponse(
            200,
            [
                'reference' => md5($label),
            ],
            $response
        );

        self::assertNotNull($this->jobRepository->get());

        $job = $this->jobRepository->get();
        self::assertSame($label, $job->getLabel());
        self::assertSame($eventDeliveryUrl, $job->getEventDeliveryUrl());
        self::assertSame($maximumDuration, $job->getMaximumDurationInSeconds());

        self::assertSame(array_keys($expectedStoredSources), $this->sourceRepository->findAllPaths());

        foreach ($this->sourceRepository->findAll() as $source) {
            $expectedSourceData = $expectedStoredSources[$source->getPath()];
            self::assertIsArray($expectedSourceData);

            self::assertArrayHasKey('type', $expectedSourceData);
            self::assertSame($expectedSourceData['type'], $source->getType());

            self::assertArrayHasKey('contentFixture', $expectedSourceData);

            self::assertTrue($this->sourceFileInspector->has($source->getPath()));
            self::assertSame(
                trim($this->fixtureReader->read($expectedSourceData['contentFixture'])),
                trim($this->sourceFileInspector->read($source->getPath()))
            );
        }
    }

    /**
     * @return array<mixed>
     */
    public function createSuccessDataProvider(): array
    {
        return [
            'single source file, test only' => [
                'manifestPaths' => [
                    'Test/chrome-open-index.yml'
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index.yml'
                ],
                'expectedStoredSources' => [
                    'Test/chrome-open-index.yml' => [
                        'type' => Source::TYPE_TEST,
                        'contentFixture' => 'Test/chrome-open-index.yml',
                    ],
                ]
            ],
            'single source file, test only with intentionally invalid yaml' => [
                'manifestPaths' => [
                    'Test/chrome-open-index.yml',
                    'InvalidTest/invalid-yaml.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index.yml',
                    'InvalidTest/invalid-yaml.yml',
                ],
                'expectedStoredSources' => [
                    'Test/chrome-open-index.yml' => [
                        'type' => Source::TYPE_TEST,
                        'contentFixture' => 'Test/chrome-open-index.yml',
                    ],
                    'InvalidTest/invalid-yaml.yml' => [
                        'type' => Source::TYPE_TEST,
                        'contentFixture' => 'InvalidTest/invalid-yaml.yml',
                    ],
                ]
            ],
            'multiple source files' => [
                'manifestPaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/firefox-open-index.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/firefox-open-index.yml',
                    'Page/index.yml',
                ],
                'expectedStoredSources' => [
                    'Test/chrome-open-index.yml' => [
                        'type' => Source::TYPE_TEST,
                        'contentFixture' => 'Test/chrome-open-index.yml',
                    ],
                    'Test/firefox-open-index.yml' => [
                        'type' => Source::TYPE_TEST,
                        'contentFixture' => 'Test/firefox-open-index.yml',
                    ],
                    'Page/index.yml' => [
                        'type' => Source::TYPE_RESOURCE,
                        'contentFixture' => 'Page/index.yml',
                    ],
                ]
            ],
        ];
    }

    /**
     * @dataProvider jobReadyEventContainsManifestPathsDataProvider
     *
     * @param string[] $manifestPaths
     * @param string[] $sourcePaths
     */
    public function testJobReadyEventContainsManifestPaths(array $manifestPaths, array $sourcePaths): void
    {
        $controller = new JobController($this->jobRepository);

        $yamlSourceCollectionFactory = self::getContainer()->get(YamlSourceCollectionFactory::class);
        \assert($yamlSourceCollectionFactory instanceof YamlSourceCollectionFactory);

        $sourceFactory = self::getContainer()->get(SourceFactory::class);
        \assert($sourceFactory instanceof SourceFactory);

        $eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($eventDispatcher instanceof EventDispatcherInterface);

        $eventDispatcher = \Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher
            ->shouldReceive('dispatch')
            ->withArgs(function (JobStartedEvent $event) use ($manifestPaths) {
                $eventPayload = $event->getPayload();
                self::assertArrayHasKey('tests', $eventPayload);
                self::assertSame($manifestPaths, $eventPayload['tests']);

                return true;
            })
        ;

        $yamlFileCollectionDeserializer = self::getContainer()->get(Deserializer::class);
        \assert($yamlFileCollectionDeserializer instanceof Deserializer);

        $sourceRepository = self::getContainer()->get(SourceRepository::class);
        \assert($sourceRepository instanceof SourceRepository);

        $referenceFactory = self::getContainer()->get(ReferenceFactory::class);
        \assert($referenceFactory instanceof ReferenceFactory);

        $request = new CreateJobRequest(
            md5((string) rand()),
            md5((string) rand()),
            rand(1, 1000),
            $this->createJobSourceFactory->create($manifestPaths, $sourcePaths)
        );

        $controller->create(
            $yamlSourceCollectionFactory,
            $sourceFactory,
            $eventDispatcher,
            \Mockery::mock(ErrorResponseFactory::class),
            $yamlFileCollectionDeserializer,
            $sourceRepository,
            $referenceFactory,
            $request
        );
    }

    /**
     * @return array<mixed>
     */
    public function jobReadyEventContainsManifestPathsDataProvider(): array
    {
        return [
            'single source file, test only' => [
                'manifestPaths' => [
                    'Test/chrome-open-index.yml'
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index.yml'
                ],
            ],
            'single source file, test only with intentionally invalid yaml' => [
                'manifestPaths' => [
                    'Test/chrome-open-index.yml',
                    'InvalidTest/invalid-yaml.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index.yml',
                    'InvalidTest/invalid-yaml.yml',
                ],
            ],
            'multiple source files' => [
                'manifestPaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/firefox-open-index.yml',
                ],
                'sourcePaths' => [
                    'Test/chrome-open-index.yml',
                    'Test/firefox-open-index.yml',
                    'Page/index.yml',
                ],
            ],
        ];
    }

    public function testStatusNoJob(): void
    {
        $response = $this->clientRequestSender->getStatus();

        $this->jsonResponseAsserter->assertJsonResponse(400, [], $response);
    }

    /**
     * @dataProvider statusDataProvider
     *
     * @param array<mixed> $expectedResponseData
     */
    public function testStatusHasJob(EnvironmentSetup $setup, array $expectedResponseData): void
    {
        $this->environmentFactory->create($setup);

        $response = $this->clientRequestSender->getStatus();

        $this->jsonResponseAsserter->assertJsonResponse(200, $expectedResponseData, $response);
    }

    /**
     * @return array<mixed>
     */
    public function statusDataProvider(): array
    {
        return [
            'new job, has sources, no tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())
                            ->withLabel('label content')
                            ->withEventDeliveryUrl('http://example.com/events')
                            ->withMaximumDurationInSeconds(11)
                            ->withTestPaths([
                                'Test/test1.yml',
                                'Test/test2.yml',
                                'Test/test3.yml',
                            ])
                    )->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                        (new SourceSetup())->withPath('Test/test3.yml'),
                    ]),
                'expectedResponseData' => [
                    'label' => 'label content',
                    'event_delivery_url' => 'http://example.com/events',
                    'maximum_duration_in_seconds' => 11,

                    'sources' => [
                        'Test/test1.yml',
                        'Test/test2.yml',
                        'Test/test3.yml',
                    ],
                    'compilation_state' => 'running',
                    'execution_state' => 'awaiting',
                    'event_delivery_state' => 'awaiting',
                    'test_paths' => [
                        'Test/test1.yml',
                        'Test/test2.yml',
                        'Test/test3.yml',
                    ],
                    'tests' => [],
                ],
            ],
            'new job, has sources, has tests, compilation not complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())
                            ->withLabel('label content')
                            ->withEventDeliveryUrl('http://example.com/events')
                            ->withMaximumDurationInSeconds(12)
                            ->withTestPaths([
                                'Test/test1.yml',
                                'Test/test2.yml',
                                'Test/test3.yml',
                            ])
                    )->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                        (new SourceSetup())->withPath('Test/test3.yml'),
                    ])->withTestSetups([
                        (new TestSetup())
                            ->withSource('{{ compiler_source_directory }}/Test/test1.yml')
                            ->withTarget('{{ compiler_target_directory }}/GeneratedTest1.php')
                            ->withStepCount(3),
                        (new TestSetup())
                            ->withSource('{{ compiler_source_directory }}/Test/test2.yml')
                            ->withTarget('{{ compiler_target_directory }}/GeneratedTest2.php')
                            ->withStepCount(2),
                    ]),
                'expectedResponseData' => [
                    'label' => 'label content',
                    'event_delivery_url' => 'http://example.com/events',
                    'maximum_duration_in_seconds' => 12,
                    'sources' => [
                        'Test/test1.yml',
                        'Test/test2.yml',
                        'Test/test3.yml',
                    ],
                    'compilation_state' => 'running',
                    'execution_state' => 'awaiting',
                    'event_delivery_state' => 'awaiting',
                    'test_paths' => [
                        'Test/test1.yml',
                        'Test/test2.yml',
                        'Test/test3.yml',
                    ],
                    'tests' => [
                        [
                            'configuration' => [
                                'browser' => 'chrome',
                                'url' => 'http://example.com',
                            ],
                            'source' => 'Test/test1.yml',
                            'target' => 'GeneratedTest1.php',
                            'step_count' => 3,
                            'state' => 'awaiting',
                            'position' => 1,
                        ],
                        [
                            'configuration' => [
                                'browser' => 'chrome',
                                'url' => 'http://example.com',
                            ],
                            'source' => 'Test/test2.yml',
                            'target' => 'GeneratedTest2.php',
                            'step_count' => 2,
                            'state' => 'awaiting',
                            'position' => 2,
                        ],
                    ],
                ],
            ],
        ];
    }
}
