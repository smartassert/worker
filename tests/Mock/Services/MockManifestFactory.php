<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Model\Manifest;
use App\Services\ManifestFactory;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MockManifestFactory
{
    private ManifestFactory $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(ManifestFactory::class);
    }

    public function getMock(): ManifestFactory
    {
        return $this->mock;
    }

    public function withCreateFromUploadedFileCall(UploadedFile $uploadedFile, Manifest $manifest): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('createFromUploadedFile')
            ->with($uploadedFile)
            ->andReturn($manifest)
        ;

        return $this;
    }

    public function withCreateFromUploadedFileCallThrowingException(
        UploadedFile $uploadedFile,
        \Exception $exception
    ): self {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('createFromUploadedFile')
            ->with($uploadedFile)
            ->andThrow($exception)
        ;

        return $this;
    }
}
