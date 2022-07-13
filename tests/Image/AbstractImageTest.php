<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Tests\Services\Asserter\SerializedJobAsserter;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\Serializer as YamlFileCollectionSerializer;
use SmartAssert\YamlFile\FileHashes\Serializer as FileHashesSerializer;
use SmartAssert\YamlFile\YamlFile;
use Symfony\Component\Yaml\Dumper;

abstract class AbstractImageTest extends TestCase
{
    private const JOB_URL = 'https://localhost:/job';

    private Client $httpClient;
    private SerializedJobAsserter $jobAsserter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new Client(['verify' => false]);
        $this->jobAsserter = new SerializedJobAsserter();
    }

    protected function makeGetJobRequest(): ResponseInterface
    {
        return $this->httpClient->sendRequest(new Request('GET', self::JOB_URL));
    }

    /**
     * @param array<mixed> $parameters
     */
    protected function makeCreateJobRequest(array $parameters): ResponseInterface
    {
        return $this->httpClient->sendRequest(new Request(
            'POST',
            self::JOB_URL,
            [
                'content-type' => 'application/x-www-form-urlencoded',
            ],
            http_build_query($parameters)
        ));
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
     * @param string[] $manifestPaths
     * @param string[] $sourcePaths
     */
    protected function createSerializedSource(array $manifestPaths, array $sourcePaths): string
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
        $this->jobAsserter->assertJob($expected, $actual);
    }
}
