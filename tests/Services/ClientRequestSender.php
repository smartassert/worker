<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Request\JobCreateRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ClientRequestSender
{
    private KernelBrowser $client;

    public function __construct(KernelBrowser $client)
    {
        $this->client = $client;
    }

    public function createJob(string $label, string $callbackUrl, int $maximumDurationInSeconds): Response
    {
        $this->client->request('POST', '/job', [
            JobCreateRequest::KEY_LABEL => $label,
            JobCreateRequest::KEY_CALLBACK_URL => $callbackUrl,
            JobCreateRequest::KEY_MAXIMUM_DURATION => $maximumDurationInSeconds,
        ]);

        return $this->client->getResponse();
    }

    /**
     * @param array<mixed> $payload
     */
    public function createCombinedJob(array $payload): Response
    {
        $this->client->request('POST', '/create_combined', $payload);

        return $this->client->getResponse();
    }

    public function getStatus(): Response
    {
        $this->client->request('GET', '/job');

        return $this->client->getResponse();
    }
}
