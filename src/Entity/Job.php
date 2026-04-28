<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\JobEndState;
use App\Repository\JobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobRepository::class)]
class Job
{
    #[ORM\Column(type: 'integer')]
    public readonly int $maximumDurationInSeconds;

    #[ORM\Column(type: 'datetime_immutable')]
    public readonly \DateTimeImmutable $startDateTime;

    #[ORM\Column(type: 'string', length: 255, nullable: true, enumType: JobEndState::class)]
    public ?JobEndState $endState;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private readonly string $eventAddUrl;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'simple_array')]
    private readonly array $testPaths;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private readonly string $label;

    /**
     * @param non-empty-string             $label
     * @param non-empty-string             $eventAddUrl
     * @param array<int, non-empty-string> $testPaths
     */
    public function __construct(
        string $label,
        string $eventAddUrl,
        int $maximumDurationInSeconds,
        array $testPaths
    ) {
        $this->label = $label;
        $this->eventAddUrl = $eventAddUrl;
        $this->maximumDurationInSeconds = $maximumDurationInSeconds;
        $this->testPaths = $testPaths;
        $this->startDateTime = new \DateTimeImmutable();
        $this->endState = null;
    }

    public function setEndState(JobEndState $state): void
    {
        $this->endState = $state;
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        \assert('' !== $this->label);

        return $this->label;
    }

    /**
     * @return non-empty-string
     */
    public function getEventAddUrl(): string
    {
        \assert('' !== $this->eventAddUrl);

        return $this->eventAddUrl;
    }

    /**
     * @return array<int, non-empty-string>
     */
    public function getTestPaths(): array
    {
        return $this->testPaths;
    }
}
