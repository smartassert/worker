<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Job implements \JsonSerializable
{
    public const ID = 1;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private int $id = self::ID;

    /**
     * @ORM\Column(type="string", length=32, nullable=false, unique=true)
     */
    private ?string $label = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $callbackUrl;

    /**
     * @ORM\Column(type="integer")
     */
    private int $maximumDurationInSeconds;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private ?\DateTimeImmutable $startDateTime = null;

    public static function create(string $label, string $callbackUrl, int $maximumDurationInSeconds): self
    {
        $job = new Job();

        $job->label = $label;
        $job->callbackUrl = $callbackUrl;
        $job->maximumDurationInSeconds = $maximumDurationInSeconds;

        return $job;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
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
            'callback_url' => $this->callbackUrl,
            'maximum_duration_in_seconds' => $this->maximumDurationInSeconds,
        ];
    }
}
