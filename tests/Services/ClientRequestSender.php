<?php

declare(strict_types=1);

namespace App\Tests\Services;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ClientRequestSender
{
    private KernelBrowser $client;

    public function __construct(KernelBrowser $client)
    {
        $this->client = $client;
    }

    /**
     * @param array<mixed> $payload
     */
    public function createJob(array $payload): Response
    {
        $this->client->request('POST', '/job', $payload);

        return $this->client->getResponse();
    }

    public function getJobStatus(): Response
    {
        $this->client->request('GET', '/job');

        return $this->client->getResponse();
    }

    public function getApplicationState(): Response
    {
        $this->client->request('GET', '/application_state');

        return $this->client->getResponse();
    }
}
