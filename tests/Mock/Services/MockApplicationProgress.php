<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use App\Services\ApplicationProgress;
use Mockery\MockInterface;

class MockApplicationProgress
{
    private ApplicationProgress $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(ApplicationProgress::class);
    }

    public function getMock(): ApplicationProgress
    {
        return $this->mock;
    }

    /**
     * @param array<ApplicationProgress::STATE_*> $states
     */
    public function withIsCall(bool $is, ...$states): self
    {
        if (false === $this->mock instanceof MockInterface) {
            return $this;
        }

        $this->mock
            ->shouldReceive('is')
            ->with(...$states)
            ->andReturn($is)
        ;

        return $this;
    }
}
