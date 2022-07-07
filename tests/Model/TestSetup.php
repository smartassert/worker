<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Enum\TestState;

class TestSetup
{
    private string $browser;
    private string $url;

    /**
     * @var non-empty-string
     */
    private string $source;

    /**
     * @var non-empty-string
     */
    private string $target;

    /**
     * @var non-empty-string[]
     */
    private array $stepNames;

    private TestState $state;

    public function __construct()
    {
        $this->browser = 'chrome';
        $this->url = 'http://example.com';
        $this->source = '/app/source/Test/test.yml';
        $this->target = '/app/tests/GeneratedTest.php';
        $this->stepNames = ['step 1'];
        $this->state = TestState::AWAITING;
    }

    public function getBrowser(): string
    {
        return $this->browser;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return non-empty-string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return non-empty-string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @return non-empty-string[]
     */
    public function getStepNames(): array
    {
        return $this->stepNames;
    }

    public function getState(): TestState
    {
        return $this->state;
    }

    /**
     * @param non-empty-string $source
     */
    public function withSource(string $source): self
    {
        $new = clone $this;
        $new->source = $source;

        return $new;
    }

    /**
     * @param non-empty-string $target
     */
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

    /**
     * @param non-empty-string[] $stepNames
     */
    public function withStepNames(array $stepNames): self
    {
        $new = clone $this;
        $new->stepNames = $stepNames;

        return $new;
    }
}
