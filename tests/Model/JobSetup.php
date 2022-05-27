<?php

declare(strict_types=1);

namespace App\Tests\Model;

class JobSetup
{
    /**
     * @var non-empty-string
     */
    private string $label;
    private string $eventDeliveryUrl;
    private int $maximumDurationInSeconds;

    /**
     * @var string[]
     */
    private array $localSourcePaths;

    public function __construct()
    {
        $this->label = md5('label content');
        $this->eventDeliveryUrl = 'http://example.com/events';
        $this->maximumDurationInSeconds = 600;
        $this->localSourcePaths = [];
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getEventDeliveryUrl(): string
    {
        return $this->eventDeliveryUrl;
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
     * @param non-empty-string $label
     */
    public function withLabel(string $label): self
    {
        $new = clone $this;
        $new->label = $label;

        return $new;
    }

    public function withEventDeliveryUrl(string $url): self
    {
        $new = clone $this;
        $new->eventDeliveryUrl = $url;

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
}
