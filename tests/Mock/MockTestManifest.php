<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use Mockery\MockInterface;
use webignition\BasilCompilerModels\Model\TestManifest;

class MockTestManifest
{
    private TestManifest $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(TestManifest::class);
    }

    public function getMock(): TestManifest
    {
        return $this->mock;
    }

    /**
     * @param non-empty-string[] $stepNames
     */
    public function withGetStepNamesCall(array $stepNames): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getStepNames')
            ->andReturn($stepNames)
        ;

        return $this;
    }

    public function withGetBrowserCall(string $browser): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getBrowser')
            ->andReturn($browser)
        ;

        return $this;
    }

    public function withGetUrlCall(string $url): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getUrl')
            ->andReturn($url)
        ;

        return $this;
    }

    public function withGetSourceCall(string $source): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getSource')
            ->andReturn($source)
        ;

        return $this;
    }

    public function withGetTargetCall(string $target): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('getTarget')
            ->andReturn($target)
        ;

        return $this;
    }
}
