<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use Mockery\MockInterface;
use webignition\BasilCompilerModels\TestManifest;

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
}
