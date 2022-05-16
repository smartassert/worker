<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\TestConfiguration;
use App\Entity\TestState;

class TestSetup
{
    private TestConfiguration $configuration;
    private string $source;
    private string $target;
    private int $stepCount;
    private TestState $state;

    public function __construct()
    {
        $this->configuration = TestConfiguration::create('chrome', 'http://example.com');
        $this->source = '/app/source/Test/test.yml';
        $this->target = '/app/tests/GeneratedTest.php';
        $this->stepCount = 1;
        $this->state = TestState::AWAITING;
    }

    public function getConfiguration(): TestConfiguration
    {
        return $this->configuration;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getStepCount(): int
    {
        return $this->stepCount;
    }

    public function getState(): TestState
    {
        return $this->state;
    }

    public function withSource(string $source): self
    {
        $new = clone $this;
        $new->source = $source;

        return $new;
    }

    public function withTarget(string $target): self
    {
        $new = clone $this;
        $new->target = $target;

        return $new;
    }

    public function withState(TestState $state): self
    {
        $new = clone $this;
        $new->state = $state;

        return $new;
    }

    public function withStepCount(int $stepCount): self
    {
        $new = clone $this;
        $new->stepCount = $stepCount;

        return $new;
    }
}
