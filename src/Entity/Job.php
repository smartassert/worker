<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
class Job implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $label;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $eventDeliveryUrl;

    #[ORM\Column(type: 'integer')]
    private int $maximumDurationInSeconds;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $startDateTime = null;

    public static function create(string $label, string $eventDeliveryUrl, int $maximumDurationInSeconds): self
    {
        $job = new Job();

        $job->label = $label;
        $job->eventDeliveryUrl = $eventDeliveryUrl;
        $job->maximumDurationInSeconds = $maximumDurationInSeconds;

        return $job;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getEventDeliveryUrl(): ?string
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
        ];
    }
}
