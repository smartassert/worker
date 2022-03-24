<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Model\UploadedFileKey;
use App\Request\AddSerializedSourceRequest;
use App\Request\AddSourcesRequest;
use App\Request\JobCreateRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    /**
     * @param UploadedFile[] $sourceUploadedFiles
     */
    public function addJobSources(UploadedFile $manifest, array $sourceUploadedFiles): Response
    {
        $manifestKey = new UploadedFileKey(AddSourcesRequest::KEY_MANIFEST);

        $requestFiles = array_merge(
            [
                $manifestKey->encode() => $manifest,
            ],
            $sourceUploadedFiles
        );

        $this->client->request(
            'POST',
            '/add-sources',
            [],
            $requestFiles
        );

        return $this->client->getResponse();
    }

    public function getStatus(): Response
    {
        $this->client->request('GET', '/job');

        return $this->client->getResponse();
    }

    public function addSerializedSource(string $source): Response
    {
        $this->client->request(
            method: 'POST',
            uri: '/add-sources-as-single-file',
            parameters: [
                AddSerializedSourceRequest::KEY_SOURCE => $source,
            ],
        );

        return $this->client->getResponse();
    }
}
