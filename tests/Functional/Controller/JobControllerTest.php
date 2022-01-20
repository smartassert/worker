<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Job;
use App\Services\EntityStore\JobStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Model\EnvironmentSetup;
use App\Tests\Model\JobSetup;
use App\Tests\Model\SourceSetup;
use App\Tests\Model\TestSetup;
use App\Tests\Services\Asserter\JsonResponseAsserter;
use App\Tests\Services\ClientRequestSender;
use App\Tests\Services\EnvironmentFactory;

class JobControllerTest extends AbstractBaseFunctionalTest
{
    private JobStore $jobStore;
    private ClientRequestSender $clientRequestSender;
    private EnvironmentFactory $environmentFactory;
    private JsonResponseAsserter $jsonResponseAsserter;

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
    }

    public function testCreate(): void
    {
        self::assertFalse($this->jobStore->has());

        $label = md5('label content');
        $callbackUrl = 'http://example.com/callback';
        $maximumDurationInSeconds = 600;

        $response = $this->clientRequestSender->createJob($label, $callbackUrl, $maximumDurationInSeconds);

        $this->jsonResponseAsserter->assertJsonResponse(200, [], $response);

        self::assertTrue($this->jobStore->has());
        self::assertEquals(
            Job::create($label, $callbackUrl, $maximumDurationInSeconds),
            $this->jobStore->get()
        );
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
    public function testStatus(EnvironmentSetup $setup, array $expectedResponseData): void
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
                            ->withSource('tests/Fixtures/CompilerSource/Test/test1.yml')
                            ->withTarget('tests/Fixtures/CompilerTarget/GeneratedTest1.php')
                            ->withStepCount(3),
                        (new TestSetup())
                            ->withSource('tests/Fixtures/CompilerSource/Test/test2.yml')
                            ->withTarget('tests/Fixtures/CompilerTarget/GeneratedTest2.php')
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
