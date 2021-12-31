<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MockUploadedFile
{
    private UploadedFile $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(UploadedFile::class);
    }

    public function getMock(): UploadedFile
    {
        return $this->mock;
    }

    public function withGetErrorCall(int $error): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getError')
            ->andReturn($error)
        ;

        return $this;
    }

    public function withGetPathnameCall(string $pathname): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getPathname')
            ->andReturn($pathname)
        ;

        return $this;
    }

    public function withGetClientMimeTypeCall(string $mimeType): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getClientMimeType')
            ->andReturn($mimeType)
        ;

        return $this;
    }
}
