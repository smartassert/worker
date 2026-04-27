<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Services\Compiler;
use Mockery\MockInterface;
use webignition\BasilCompilerModels\Model\OutputInterface;

class MockCompiler
{
    private Compiler $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(Compiler::class);
    }

    public function getMock(): Compiler
    {
        return $this->mock;
    }

    public function withCompileCall(string $source, int $timeoutInSeconds, \Exception|OutputInterface $outcome): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $expectation = $this->mock
            ->shouldReceive('compile')
            ->with($source, $timeoutInSeconds)
        ;

        if ($outcome instanceof \Exception) {
            $expectation->andThrow($outcome);
        } else {
            $expectation->andReturn($outcome);
        }

        return $this;
    }
}
