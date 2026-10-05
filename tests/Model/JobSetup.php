<?php

declare(strict_types=1);

namespace App\Tests\Model;

class JobSetup
{
    /**
     * @var non-empty-string
     */
    private string $label;

    /**
     * @var non-empty-string
     */
    private string $eventNotifyUrl;
    private int $maximumDurationInSeconds;

    /**
     * @var string[]
     */
    private array $localSourcePaths;

    /**
     * @var non-empty-string[]
     */
    private array $testPaths;

    /**
     * @var ?non-empty-string
     */
    private ?string $stateNotifyUrl;

    public function __construct()
    {
        $this->label = md5('label content');
        $this->eventNotifyUrl = 'https://results.example.com';
        $this->maximumDurationInSeconds = 600;
        $this->localSourcePaths = [];
        $this->testPaths = ['test.yml'];
        $this->stateNotifyUrl = null;
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return non-empty-string
     */
    public function getEventNotifyUrl(): string
    {
        return $this->eventNotifyUrl;
    }

    public function getMaximumDurationInSeconds(): int
    {
        return $this->maximumDurationInSeconds;
    }

    /**
     * @return string[]
     */
    public function getLocalSourcePaths(): array
    {
        return $this->localSourcePaths;
    }

    /**
     * @return non-empty-string[]
     */
    public function getTestPaths(): array
    {
        return $this->testPaths;
    }

    /**
     * @param non-empty-string $label
     */
    public function withLabel(string $label): self
    {
        $new = clone $this;
        $new->label = $label;

        return $new;
    }

    /**
     * @param non-empty-string $url
     */
    public function withEventNotifyUrl(string $url): self
    {
        $new = clone $this;
        $new->eventNotifyUrl = $url;

        return $new;
    }

    /**
     * @param string[] $localSourcePaths
     */
    public function withLocalSourcePaths(array $localSourcePaths): self
    {
        $new = clone $this;
        $new->localSourcePaths = $localSourcePaths;

        return $new;
    }

    public function withMaximumDurationInSeconds(int $maximumDurationInSeconds): self
    {
        $new = clone $this;
        $new->maximumDurationInSeconds = $maximumDurationInSeconds;

        return $new;
    }

    /**
     * @param non-empty-string[] $testPaths
     */
    public function withTestPaths(array $testPaths): self
    {
        $new = clone $this;
        $new->testPaths = $testPaths;

        return $new;
    }

    /**
     * @param non-empty-string $url
     */
    public function withStateNotifyUrl(string $url): self
    {
        $new = clone $this;
        $new->stateNotifyUrl = $url;

        return $new;
    }

    /**
     * @return ?non-empty-string
     */
    public function getStateNotifyUrl(): ?string
    {
        return $this->stateNotifyUrl;
    }
}
