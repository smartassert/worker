<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\CompilationState;
use App\Enum\EventDeliveryState;
use App\Enum\ExecutionState;
use App\Tests\Services\Asserter\SerializedJobAsserter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\Serializer as YamlFileCollectionSerializer;
use SmartAssert\YamlFile\FileHashes\Serializer as FileHashesSerializer;
use SmartAssert\YamlFile\YamlFile;
use Symfony\Component\Yaml\Dumper;

class AppTest extends TestCase
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;
    private const WAIT_TIMEOUT = self::MICROSECONDS_PER_SECOND * 60;
    private const JOB_URL = 'https://localhost:/job';

    private Client $httpClient;
    private SerializedJobAsserter $jobAsserter;
    private YamlFileCollectionSerializer $yamlFileCollectionSerializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new Client(['verify' => false]);
        $this->jobAsserter = new SerializedJobAsserter($this->httpClient);

        $this->yamlFileCollectionSerializer = new YamlFileCollectionSerializer(
            new FileHashesSerializer(
                new Dumper()
            )
        );
    }

    public function testInitialStatus(): void
    {
        try {
            $response = $this->httpClient->get(self::JOB_URL);
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

        $response = $this->httpClient->post('https://localhost/job', [
            'form_params' => [
                'label' => md5('label content'),
                'event_delivery_url' => 'http://event-receiver/events',
                'maximum_duration_in_seconds' => 600,
                'source' => $serializedSource,
            ],
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

        $this->jobAsserter->assertJob([
            'label' => md5('label content'),
            'event_delivery_url' => 'http://event-receiver/events',
            'maximum_duration_in_seconds' => 600,
            'sources' => [
                'Test/chrome-open-index.yml',
                'Test/chrome-firefox-open-index.yml',
                'Test/chrome-open-form.yml',
                'Page/index.yml',
            ],
            'compilation_state' => 'complete',
            'execution_state' => 'complete',
            'event_delivery_state' => 'complete',
            'tests' => [
                [
                    'configuration' => [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                    ],
                    'source' => 'Test/chrome-open-index.yml',
                    'step_names' => [
                        'verify page is open',
                    ],
                    'state' => 'complete',
                    'position' => 1,
                ],
                [
                    'configuration' => [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                    ],
                    'source' => 'Test/chrome-firefox-open-index.yml',
                    'step_names' => [
                        'verify page is open',
                    ],
                    'state' => 'complete',
                    'position' => 2,
                ],
                [
                    'configuration' => [
                        'browser' => 'firefox',
                        'url' => 'http://html-fixtures/index.html',
                    ],
                    'source' => 'Test/chrome-firefox-open-index.yml',
                    'step_names' => [
                        'verify page is open',
                    ],
                    'state' => 'complete',
                    'position' => 3,
                ],
                [
                    'configuration' => [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/form.html',
                    ],
                    'source' => 'Test/chrome-open-form.yml',
                    'step_names' => [
                        'verify page is open',
                    ],
                    'state' => 'complete',
                    'position' => 4,
                ],
            ],
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function getJobStatus(): array
    {
        $response = $this->httpClient->sendRequest(new Request('GET', self::JOB_URL));

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
