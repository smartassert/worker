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
    private readonly string $eventNotifyUrl;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private readonly string $eventNotifyToken;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'simple_array')]
    private readonly array $testPaths;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private readonly string $label;

    #[ORM\Column(nullable: true)]
    private ?string $stateNotifyUrl;

    /**
     * @param non-empty-string             $label
     * @param non-empty-string             $eventNotifyUrl
     * @param non-empty-string             $eventNotifyToken
     * @param array<int, non-empty-string> $testPaths
     * @param ?non-empty-string            $stateNotifyUrl
     */
    public function __construct(
        string $label,
        string $eventNotifyUrl,
        string $eventNotifyToken,
        int $maximumDurationInSeconds,
        array $testPaths,
        ?string $stateNotifyUrl,
    ) {
        $this->label = $label;
        $this->eventNotifyUrl = $eventNotifyUrl;
        $this->eventNotifyToken = $eventNotifyToken;
        $this->maximumDurationInSeconds = $maximumDurationInSeconds;
        $this->testPaths = $testPaths;
        $this->startDateTime = new \DateTimeImmutable();
        $this->endState = null;
        $this->stateNotifyUrl = $stateNotifyUrl;
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

    public function getEventNotifyUrl(): string
    {
        return $this->eventNotifyUrl;
    }

    public function getEventNotifyToken(): string
    {
        return $this->eventNotifyToken;
    }

    /**
     * @return array<int, non-empty-string>
     */
    public function getTestPaths(): array
    {
        return $this->testPaths;
    }

    public function getStateNotifyUrl(): ?string
    {
        return $this->stateNotifyUrl;
    }
}
