<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
class Job implements \JsonSerializable
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $label;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $eventDeliveryUrl;

    #[ORM\Column(type: 'integer')]
    private int $maximumDurationInSeconds;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $startDateTime = null;

    /**
     * @var array<int, string>
     */
    #[ORM\Column(type: 'simple_array')]
    private array $testPaths;

    /**
     * @param non-empty-string $label
     * @param non-empty-string $eventDeliveryUrl
     * @param array<int, string> $testPaths
     */
    public function __construct(
        string $label,
        string $eventDeliveryUrl,
        int $maximumDurationInSeconds,
        array $testPaths
    ) {
        $this->label = $label;
        $this->eventDeliveryUrl = $eventDeliveryUrl;
        $this->maximumDurationInSeconds = $maximumDurationInSeconds;
        $this->testPaths = $testPaths;
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
    public function getEventDeliveryUrl(): string
    {
        return $this->eventDeliveryUrl;
    }

    public function getMaximumDurationInSeconds(): int
    {
        return $this->maximumDurationInSeconds;
    }

    public function hasStarted(): bool
    {
        return $this->startDateTime instanceof \DateTimeInterface;
    }

    public function hasReachedMaximumDuration(): bool
    {
        if ($this->startDateTime instanceof \DateTimeInterface) {
            $duration = time() - $this->startDateTime->getTimestamp();

            return $duration >= $this->maximumDurationInSeconds;
        }

        return false;
    }

    public function setStartDateTime(): void
    {
        $this->startDateTime = new \DateTimeImmutable();
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'event_delivery_url' => $this->eventDeliveryUrl,
            'maximum_duration_in_seconds' => $this->maximumDurationInSeconds,
            'test_paths' => $this->testPaths,
        ];
    }
}
