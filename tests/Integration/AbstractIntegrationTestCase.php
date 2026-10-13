<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Services\EntityRemover;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\YamlFile\Collection\ArrayCollection;
use SmartAssert\YamlFile\Collection\Serializer as YamlFileCollectionSerializer;
use SmartAssert\YamlFile\FileHashes\Serializer as FileHashesSerializer;
use SmartAssert\YamlFile\YamlFile;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Dumper;

abstract class AbstractIntegrationTestCase extends WebTestCase
{
    private const JOB_URL = 'http://localhost:8000/job';
    private const APPLICATION_STATE_URL = 'http://localhost:8000/application_state';

    private static Client $httpClient;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$httpClient = new Client(['verify' => false]);

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        \assert($entityRemover instanceof EntityRemover);
        $entityRemover->removeAll();
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

    protected static function makeGetApplicationStateRequest(): ResponseInterface
    {
        return self::$httpClient->sendRequest(new Request('GET', self::APPLICATION_STATE_URL));
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
    protected static function fetchApplicationState(): array
    {
        $response = self::makeGetApplicationStateRequest();
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

    protected static function isApplicationComplete(): bool
    {
        $state = self::fetchApplicationState();

        return true === self::isComponentEndState($state, 'application')
            && true === self::isComponentEndState($state, 'event_delivery');
    }

    /**
     * @param array<mixed>     $data
     * @param non-empty-string $key
     */
    private static function isComponentEndState(array $data, string $key): bool
    {
        $state = $data[$key] ?? [];
        $state = is_array($state) ? $state : [];

        $metaState = $state['meta_state'] ?? [];
        $metaState = is_array($metaState) ? $metaState : [];

        $metaStateEnded = $metaState['ended'] ?? false;

        return is_bool($metaStateEnded) && $metaStateEnded;
    }
}
