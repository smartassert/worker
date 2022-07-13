<?php

declare(strict_types=1);

namespace App\Tests\Image;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractImageTest extends TestCase
{
    private const JOB_URL = 'https://localhost:/job';

    private Client $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = new Client(['verify' => false]);
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
}
