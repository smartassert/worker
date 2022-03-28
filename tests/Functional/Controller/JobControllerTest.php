<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Source;
use App\Repository\SourceRepository;
use App\Request\CreateJobRequest;
use App\Services\EntityStore\JobStore;
use App\Services\EntityStore\SourceStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\CreateJobSourceFactory;
use App\Tests\Services\EnvironmentFactory;
use App\Tests\Services\FixtureReader;
use App\Tests\Services\SourceFileInspector;

class JobControllerTest extends AbstractBaseFunctionalTest
{
    private JobStore $jobStore;
    private ClientRequestSender $clientRequestSender;
    private EnvironmentFactory $environmentFactory;
    private JsonResponseAsserter $jsonResponseAsserter;
    private SourceStore $sourceStore;
    private SourceRepository $sourceRepository;
    private SourceFileInspector $sourceFileInspector;
    private FixtureReader $fixtureReader;
    private CreateJobSourceFactory $createJobSourceFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $jobStore = self::getContainer()->get(JobStore::class);
        \assert($jobStore instanceof JobStore);
        $this->jobStore = $jobStore;

        $clientRequestSender = self::getContainer()->get(ClientRequestSender::class);
        \assert($clientRequestSender instanceof ClientRequestSender);
        $this->clientRequestSender = $clientRequestSender;

        $environmentFactory = self::getContainer()->get(EnvironmentFactory::class);
        \assert($environmentFactory instanceof EnvironmentFactory);
        $this->environmentFactory = $environmentFactory;

        $jsonResponseAsserter = self::getContainer()->get(JsonResponseAsserter::class);
        \assert($jsonResponseAsserter instanceof JsonResponseAsserter);
        $this->jsonResponseAsserter = $jsonResponseAsserter;

        $sourceStore = self::getContainer()->get(SourceStore::class);
        \assert($sourceStore instanceof SourceStore);
        $this->sourceStore = $sourceStore;

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
        self::assertFalse($this->jobStore->has());

        $response = $this->clientRequestSender->create($requestPayload);
        $this->jsonResponseAsserter->assertJsonResponse(400, $expectedResponseData, $response);

        self::assertFalse($this->jobStore->has());
    }

    /**
     * @return array<mixed>
     */
    public function createBadRequestMissingValuesDataProvider(): array
    {
        $label = 'label value';
        $callbackUrl = 'https://example.com/callback';
        $maximumDurationInSeconds = 600;
        $nonEmptySource = 'non-empty source';

        $nonEmptyPayload = [
            CreateJobRequest::KEY_LABEL => $label,
            CreateJobRequest::KEY_CALLBACK_URL => $callbackUrl,
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
            'missing values: callback_url missing' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_CALLBACK_URL => null,
                ]),
                'expectedResponseData' => [
                    'error_state' => 'callback_url/missing',
                ],
            ],
            'missing values: callback_url empty' => [
                'requestPayload' => array_merge($nonEmptyPayload, [
                    CreateJobRequest::KEY_CALLBACK_URL => '',
                ]),
                'expectedResponseData' => [
                    'error_state' => 'callback_url/missing',
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
            CreateJobRequest::KEY_CALLBACK_URL => 'https://example.com/callback',
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
            'invalid source: foo' => [
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
                    'payload' => [
                        'message' => 'Manifest is empty',
                    ],
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
                        'message' => 'Manifest content is not valid yaml',
                        'previous_message' => 'Unable to parse at line 1 (near "  invalid").',
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
                        'message' => 'Test source "Test/chrome-open-index.yml" missing',
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
        self::assertFalse($this->jobStore->has());

        $label = md5((string) rand());
        $callbackUrl = md5((string) rand());
        $maximumDuration = rand(1, 1000);

        $requestPayload = [
            CreateJobRequest::KEY_LABEL => $label,
            CreateJobRequest::KEY_CALLBACK_URL => $callbackUrl,
            CreateJobRequest::KEY_MAXIMUM_DURATION => $maximumDuration,
            CreateJobRequest::KEY_SOURCE => $this->createJobSourceFactory->create($manifestPaths, $sourcePaths),
        ];

        $response = $this->clientRequestSender->create($requestPayload);
        $this->jsonResponseAsserter->assertJsonResponse(200, [], $response);

        self::assertTrue($this->jobStore->has());

        $job = $this->jobStore->get();
        self::assertSame($label, $job->getLabel());
        self::assertSame($callbackUrl, $job->getCallbackUrl());
        self::assertSame($maximumDuration, $job->getMaximumDurationInSeconds());

        self::assertSame(array_keys($expectedStoredSources), $this->sourceStore->findAllPaths());

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
            'new job, no sources, no tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())
                            ->withLabel('label content')
                            ->withCallbackUrl('http://example.com/callback')
                            ->withMaximumDurationInSeconds(10)
                    ),
                'expectedResponseData' => [
                    'label' => 'label content',
                    'callback_url' => 'http://example.com/callback',
                    'maximum_duration_in_seconds' => 10,
                    'sources' => [],
                    'compilation_state' => 'awaiting',
                    'execution_state' => 'awaiting',
                    'callback_state' => 'awaiting',
                    'tests' => [],
                ],
            ],
            'new job, has sources, no tests' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())
                            ->withLabel('label content')
                            ->withCallbackUrl('http://example.com/callback')
                            ->withMaximumDurationInSeconds(11)
                    )->withSourceSetups([
                        (new SourceSetup())->withPath('Test/test1.yml'),
                        (new SourceSetup())->withPath('Test/test2.yml'),
                        (new SourceSetup())->withPath('Test/test3.yml'),
                    ]),
                'expectedResponseData' => [
                    'label' => 'label content',
                    'callback_url' => 'http://example.com/callback',
                    'maximum_duration_in_seconds' => 11,
                    'sources' => [
                        'Test/test1.yml',
                        'Test/test2.yml',
                        'Test/test3.yml',
                    ],
                    'compilation_state' => 'running',
                    'execution_state' => 'awaiting',
                    'callback_state' => 'awaiting',
                    'tests' => [],
                ],
            ],
            'new job, has sources, has tests, compilation not complete' => [
                'setup' => (new EnvironmentSetup())
                    ->withJobSetup(
                        (new JobSetup())
                            ->withLabel('label content')
                            ->withCallbackUrl('http://example.com/callback')
                            ->withMaximumDurationInSeconds(12)
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
                    'callback_url' => 'http://example.com/callback',
                    'maximum_duration_in_seconds' => 12,
                    'sources' => [
                        'Test/test1.yml',
                        'Test/test2.yml',
                        'Test/test3.yml',
                    ],
                    'compilation_state' => 'running',
                    'execution_state' => 'awaiting',
                    'callback_state' => 'awaiting',
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
