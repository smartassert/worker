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

    public function withCompileCall(string $source, OutputInterface $output): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('compile')
            ->with($source)
            ->andReturn($output)
        ;

        return $this;
    }
}
