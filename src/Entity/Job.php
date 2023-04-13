<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\JobEndState;
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
    #[ORM\Column(type: 'string', length: 32, nullable: false)]
    public readonly string $resultsToken;

    #[ORM\Column(type: 'integer')]
    public readonly int $maximumDurationInSeconds;

    #[ORM\Column(type: 'datetime_immutable')]
    public readonly \DateTimeImmutable $startDateTime;

    /**
     * @var array<int, non-empty-string>
     */
    #[ORM\Column(type: 'simple_array')]
    public readonly array $testPaths;

    #[ORM\Column(type: 'string', length: 255, nullable: true, enumType: JobEndState::class)]
    public ?JobEndState $endState;

    /**
     * @param non-empty-string             $label
     * @param non-empty-string             $resultsToken
     * @param array<int, non-empty-string> $testPaths
     */
    public function __construct(
        string $label,
        string $resultsToken,
        int $maximumDurationInSeconds,
        array $testPaths
    ) {
        $this->label = $label;
        $this->resultsToken = $resultsToken;
        $this->maximumDurationInSeconds = $maximumDurationInSeconds;
        $this->testPaths = $testPaths;
        $this->startDateTime = new \DateTimeImmutable();
        $this->endState = null;
    }

    public function setEndState(JobEndState $state): void
    {
        $this->endState = $state;
    }
}
