<?php

declare(strict_types=1);

namespace App\Tests\Mock\Model;

use App\Model\UploadedSourceCollection;
use Mockery\MockInterface;

class MockUploadedSourceCollection
{
    private UploadedSourceCollection | MockInterface $sources;

    public function __construct()
    {
        $this->sources = \Mockery::mock(UploadedSourceCollection::class);
    }

    public function getMock(): UploadedSourceCollection
    {
        return $this->sources;
    }

    public function withContainsCall(string $path, bool $contains): self
    {
        if ($this->sources instanceof MockInterface) {
            $this->sources
                ->shouldReceive('contains')
                ->with($path)
                ->andReturn($contains)
            ;
        }

        return $this;
    }

    public function withOffsetGetCall(string $offset, mixed $return): self
    {
        if ($this->sources instanceof MockInterface) {
            $this->sources
                ->shouldReceive('offsetGet')
                ->with($offset)
                ->andReturn($return)
            ;
        }

        return $this;
    }
}
