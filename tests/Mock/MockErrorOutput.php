<?php

declare(strict_types=1);

namespace App\Tests\Mock;

use Mockery\MockInterface;
use webignition\BasilCompilerModels\Model\ErrorOutputInterface;

class MockErrorOutput
{
    private ErrorOutputInterface $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(ErrorOutputInterface::class);
    }

    public function getMock(): ErrorOutputInterface
    {
        return $this->mock;
    }

    /**
     * @param array<mixed> $return
     */
    public function withToArrayCall(array $return): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('toArray')
            ->andReturn($return)
        ;

        return $this;
    }
}
