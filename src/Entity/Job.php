<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\JobEndedState;
use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
class Job
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    public readonly string $label;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    public readonly string $eventDeliveryUrl;

    #[ORM\Column(type: 'integer')]
    public readonly int $maximumDurationInSeconds;

    #[ORM\Column(type: 'datetime_immutable')]
    public readonly \DateTimeImmutable $startDateTime;

    /**
     * @var array<int, non-empty-string>
     */
    #[ORM\Column(type: 'simple_array')]
    public readonly array $testPaths;

    #[ORM\Column(type: 'string', length: 255, nullable: true, enumType: JobEndedState::class)]
    public ?JobEndedState $endState;

    /**
     * @param non-empty-string             $label
     * @param non-empty-string             $eventDeliveryUrl
     * @param array<int, non-empty-string> $testPaths
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
        $this->startDateTime = new \DateTimeImmutable();
        $this->endState = null;
    }

    public function setEndState(JobEndedState $state): void
    {
        $this->endState = $state;
    }
}
