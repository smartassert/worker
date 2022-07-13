<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Tests\Services\Asserter\SerializedJobAsserter;
use GuzzleHttp\Exception\ClientException;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\Serializer as YamlFileCollectionSerializer;
use SmartAssert\YamlFile\FileHashes\Serializer as FileHashesSerializer;
use SmartAssert\YamlFile\YamlFile;
use Symfony\Component\Yaml\Dumper;

class AppTest extends AbstractImageTest
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;
    private const WAIT_TIMEOUT = self::MICROSECONDS_PER_SECOND * 60;

    private SerializedJobAsserter $jobAsserter;
    private YamlFileCollectionSerializer $yamlFileCollectionSerializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobAsserter = new SerializedJobAsserter();

        $this->yamlFileCollectionSerializer = new YamlFileCollectionSerializer(
            new FileHashesSerializer(
                new Dumper()
            )
        );
    }

    public function testInitialStatus(): void
    {
        try {
            $response = $this->makeGetJobRequest();
        } catch (ClientException $exception) {
            $response = $exception->getResponse();
        }

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * @depends testInitialStatus
     */
    public function testCreateJob(): void
    {
        $yamlFiles = [];

        $yamlFiles[] = YamlFile::create(
            'manifest.yaml',
            <<< 'EOT'
            - Test/chrome-open-index.yml
            - Test/chrome-firefox-open-index.yml
            - Test/chrome-open-form.yml
            EOT
        );

        $sourcePaths = [
            'Test/chrome-open-index.yml',
            'Test/chrome-firefox-open-index.yml',
            'Test/chrome-open-form.yml',
            'Page/index.yml',
        ];

        foreach ($sourcePaths as $sourcePath) {
            $yamlFiles[] = YamlFile::create(
                $sourcePath,
                trim((string) file_get_contents(getcwd() . '/tests/Fixtures/Basil/' . $sourcePath))
            );
        }

        $yamlFileCollection = new ArrayCollection($yamlFiles);
        $serializedSource = $this->yamlFileCollectionSerializer->serialize($yamlFileCollection);

        $response = $this->makeCreateJobRequest([
            'label' => md5('label content'),
            'event_delivery_url' => 'http://event-receiver/events',
            'maximum_duration_in_seconds' => 600,
            'source' => $serializedSource,
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @depends testCreateJob
     */
    public function testCompilationExecution(): void
    {
        $duration = 0;
        $durationExceeded = false;

        while (false === $durationExceeded && false === $this->waitForApplicationToComplete()) {
            usleep(self::WAIT_INTERVAL);
            $duration += self::WAIT_INTERVAL;
            $durationExceeded = $duration >= self::WAIT_TIMEOUT;
        }

        self::assertFalse($durationExceeded);

        $this->jobAsserter->assertJob(
            [
                'label' => md5('label content'),
                'event_delivery_url' => 'http://event-receiver/events',
                'maximum_duration_in_seconds' => 600,
                'sources' => [
                    'Test/chrome-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                    'Page/index.yml',
                ],
                'application_state' => 'complete',
                'compilation_state' => 'complete',
                'execution_state' => 'complete',
                'event_delivery_state' => 'complete',
                'tests' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 1,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 2,
                    ],
                    [
                        'browser' => 'firefox',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 3,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/form.html',
                        'source' => 'Test/chrome-open-form.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 4,
                    ],
                ],
            ],
            $this->getJobStatus()
        );
    }

    /**
     * @return array<mixed>
     */
    private function getJobStatus(): array
    {
        $response = $this->makeGetJobRequest();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        self::assertIsArray($data);

        return $data;
    }

    private function waitForApplicationToComplete(): bool
    {
        $jobStatus = $this->getJobStatus();

        return CompilationState::COMPLETE->value === $jobStatus['compilation_state']
            && ExecutionState::COMPLETE->value === $jobStatus['execution_state']
            && EventDeliveryState::COMPLETE->value === $jobStatus['event_delivery_state'];
    }
}
