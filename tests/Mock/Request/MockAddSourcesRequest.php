<?php

declare(strict_types=1);

namespace App\Tests\Mock\Request;

use App\Model\UploadedSourceCollection;
use App\Request\AddSourcesRequest;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MockAddSourcesRequest
{
    /**
     * @var AddSourcesRequest|MockInterface
     */
    private AddSourcesRequest $addSourcesRequest;

    public function __construct()
    {
        $this->addSourcesRequest = \Mockery::mock(AddSourcesRequest::class);
    }

    public function getMock(): AddSourcesRequest
    {
        return $this->addSourcesRequest;
    }

    public function withGetManifestCall(?UploadedFile $uploadedFile): self
    {
        $this->addSourcesRequest
            ->shouldReceive('getManifest')
            ->andReturn($uploadedFile)
        ;

        return $this;
    }

    public function withGetUploadedSourcesCall(UploadedSourceCollection $sources): self
    {
        $this->addSourcesRequest
            ->shouldReceive('getUploadedSources')
            ->andReturn($sources)
        ;

        return $this;
    }
}
