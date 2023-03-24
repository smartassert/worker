<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Tests\Services\Asserter\ApplicationResponseDataAsserter;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\Serializer as YamlFileCollectionSerializer;
use SmartAssert\YamlFile\FileHashes\Serializer as FileHashesSerializer;
use SmartAssert\YamlFile\YamlFile;
use Symfony\Component\Yaml\Dumper;

abstract class AbstractImageTestCase extends TestCase
{
    private const JOB_URL = 'http://localhost:8000/job';
    private const APPLICATION_STATE_URL = 'http://localhost:8000/application_state';
    private const EVENT_URL = 'http://localhost:8000/event/%d';

    private static Client $httpClient;
    private static ApplicationResponseDataAsserter $responseDataAsserter;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$httpClient = new Client(['verify' => false]);
        self::$responseDataAsserter = new ApplicationResponseDataAsserter();
    }

    protected function makeGetJobRequest(): ResponseInterface
    {
        return self::$httpClient->sendRequest(new Request('GET', self::JOB_URL));
    }

    /**
     * @param array<mixed> $parameters
     */
    protected static function makeCreateJobRequest(array $parameters): ResponseInterface
    {
        return self::$httpClient->sendRequest(new Request(
            'POST',
            self::JOB_URL,
            [
                'content-type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query($parameters)
        ));
    }

    protected function makeGetApplicationStateRequest(): ResponseInterface
    {
        return self::$httpClient->sendRequest(new Request('GET', self::APPLICATION_STATE_URL));
    }

    protected function makeGetEventRequest(int $id): ResponseInterface
    {
        return self::$httpClient->sendRequest(new Request('GET', sprintf(self::EVENT_URL, $id)));
    }

    /**
     * @return array<mixed>
     */
    protected function fetchJob(): array
    {
        $response = $this->makeGetJobRequest();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        \assert(is_array($data));

        return $data;
    }

    /**
     * @return array<mixed>
     */
    protected function fetchApplicationState(): array
    {
        $response = $this->makeGetApplicationStateRequest();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        \assert(is_array($data));

        return $data;
    }

    /**
     * @param string[] $manifestPaths
     * @param string[] $sourcePaths
     */
    protected static function createSerializedSource(array $manifestPaths, array $sourcePaths): string
    {
        $manifestContent = '';

        foreach ($manifestPaths as $manifestPath) {
            $manifestContent .= '- ' . $manifestPath . "\n";
        }
        $manifestContent = trim($manifestContent);

        $yamlFiles = [];
        $yamlFiles[] = YamlFile::create('manifest.yaml', $manifestContent);

        foreach ($sourcePaths as $sourcePath) {
            $yamlFiles[] = YamlFile::create(
                $sourcePath,
                trim((string) file_get_contents(getcwd() . '/tests/Fixtures/Basil/' . $sourcePath))
            );
        }

        $yamlFileCollection = new ArrayCollection($yamlFiles);

        $yamlFileCollectionSerializer = new YamlFileCollectionSerializer(
            new FileHashesSerializer(
                new Dumper()
            )
        );

        return $yamlFileCollectionSerializer->serialize($yamlFileCollection);
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    protected function assertJob(array $expected, array $actual): void
    {
        self::$responseDataAsserter->assertJob($expected, $actual);
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    protected function assertApplicationState(array $expected, array $actual): void
    {
        self::$responseDataAsserter->assertApplicationState($expected, $actual);
    }
}
