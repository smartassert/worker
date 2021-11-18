<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Model\UploadedFileKey;
use App\Request\AddSourcesRequest;
use App\Services\CallbackState;
use App\Services\CompilationState;
use App\Services\ExecutionState;
use App\Tests\Services\Asserter\SerializedJobAsserter;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

class AppTest extends TestCase
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND * 1;
    private const WAIT_TIMEOUT = self::MICROSECONDS_PER_SECOND * 60;

    private Client $httpClient;
    private SerializedJobAsserter $jobAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new Client([
            'verify' => false,
        ]);
        $this->jobAsserter = new SerializedJobAsserter($this->httpClient);
    }

    public function testInitialStatus(): void
    {
        try {
            $response = $this->httpClient->get('https://localhost:/job');
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
        $response = $this->httpClient->post('https://localhost/job', [
            'form_params' => [
                'label' => md5('label content'),
                'callback-url' => 'http://example.com/callback',
                'maximum-duration-in-seconds' => 600,
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());

        $this->jobAsserter->assertJob([
            'label' => md5('label content'),
            'callback_url' => 'http://example.com/callback',
            'maximum_duration_in_seconds' => 600,
            'sources' => [],
            'compilation_state' => 'awaiting',
            'execution_state' => 'awaiting',
            'callback_state' => 'awaiting',
            'tests' => [],
        ]);
    }

    /**
     * @depends testCreateJob
     */
    public function testAddSources(): void
    {
        $manifestKey = new UploadedFileKey(AddSourcesRequest::KEY_MANIFEST);

        $this->httpClient->post('https://localhost/add-sources', [
            'multipart' => [
                [
                    'name' => $manifestKey->encode(),
                    'contents' => file_get_contents(
                        getcwd() . '/tests/Fixtures/Manifest/manifest.txt'
                    ),
                    'filename' => 'manifest.txt'
                ],
                $this->createFileUploadData('Test/chrome-open-index.yml'),
                $this->createFileUploadData('Test/chrome-firefox-open-index.yml'),
                $this->createFileUploadData('Test/chrome-open-form.yml'),
                $this->createFileUploadData('Page/index.yml'),
            ],
        ]);

        $this->jobAsserter->assertJob([
            'label' => md5('label content'),
            'callback_url' => 'http://example.com/callback',
            'maximum_duration_in_seconds' => 600,
            'sources' => [
                'Test/chrome-open-index.yml',
                'Test/chrome-firefox-open-index.yml',
                'Test/chrome-open-form.yml',
                'Page/index.yml',
            ],
            'compilation_state' => 'running',
            'execution_state' => 'awaiting',
            'callback_state' => 'awaiting',
            'tests' => [],
        ]);
    }

    /**
     * @depends testAddSources
     */
    public function testCompilationExecution(): void
    {
        $duration = 0;
        $durationExceeded = false;

        while (
            false === $durationExceeded
            && false === $this->waitForApplicationState(
                CompilationState::STATE_COMPLETE,
                ExecutionState::STATE_COMPLETE,
                CallbackState::STATE_COMPLETE,
            )
        ) {
            usleep(self::WAIT_INTERVAL);
            $duration += self::WAIT_INTERVAL;
            $durationExceeded = $duration >= self::WAIT_TIMEOUT;
        }

        self::assertFalse($durationExceeded);

        $this->jobAsserter->assertJob([
            'label' => md5('label content'),
            'callback_url' => 'http://example.com/callback',
            'maximum_duration_in_seconds' => 600,
            'sources' => [
                'Test/chrome-open-index.yml',
                'Test/chrome-firefox-open-index.yml',
                'Test/chrome-open-form.yml',
                'Page/index.yml',
            ],
            'compilation_state' => 'complete',
            'execution_state' => 'complete',
            'callback_state' => 'complete',
            'tests' => [
                [
                    'configuration' => [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                    ],
                    'source' => 'Test/chrome-open-index.yml',
                    'step_count' => 1,
                    'state' => 'complete',
                    'position' => 1,
                ],
                [
                    'configuration' => [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                    ],
                    'source' => 'Test/chrome-firefox-open-index.yml',
                    'step_count' => 1,
                    'state' => 'complete',
                    'position' => 2,
                ],
                [
                    'configuration' => [
                        'browser' => 'firefox',
                        'url' => 'http://html-fixtures/index.html',
                    ],
                    'source' => 'Test/chrome-firefox-open-index.yml',
                    'step_count' => 1,
                    'state' => 'complete',
                    'position' => 3,
                ],
                [
                    'configuration' => [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/form.html',
                    ],
                    'source' => 'Test/chrome-open-form.yml',
                    'step_count' => 1,
                    'state' => 'complete',
                    'position' => 4,
                ],
            ],
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function getJsonResponse(string $url): array
    {
        $response = $this->httpClient->sendRequest(new Request('GET', $url));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $body = $response->getBody()->getContents();

        return json_decode($body, true);
    }

    /**
     * @return array<string, string>
     */
    private function createFileUploadData(string $path): array
    {
        return [
            'name' => base64_encode($path),
            'contents' => (string) file_get_contents(getcwd() . '/tests/Fixtures/Basil/' . $path),
            'filename' => $path
        ];
    }

    /**
     * @param CompilationState::STATE_* $compilationState
     * @param ExecutionState::STATE_*   $executionState
     * @param CallbackState::STATE_*    $callbackState
     */
    private function waitForApplicationState(
        string $compilationState,
        string $executionState,
        string $callbackState,
    ): bool {
        $jobStatus = $this->getJsonResponse('https://localhost/job');

        return $compilationState === $jobStatus['compilation_state']
            && $executionState === $jobStatus['execution_state']
            && $callbackState === $jobStatus['callback_state'];
    }
}
