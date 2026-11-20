<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TestState;
use App\Repository\TestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestRepository::class)]
class Test
{
    #[ORM\Column(length: 255)]
    public readonly string $browser;

    #[ORM\Column(length: 255)]
    public readonly string $url;

    #[ORM\Column(unique: true, nullable: false)]
    public readonly int $position;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private readonly array $stepNames;

    #[ORM\Column(type: Types::TEXT)]
    private readonly string $source;

    #[ORM\Column(type: Types::TEXT)]
    private readonly string $target;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255, enumType: TestState::class)]
    private TestState $state;

    /**
     * @param non-empty-string   $source
     * @param non-empty-string   $target
     * @param non-empty-string[] $stepNames
     */
    public function __construct(
        string $browser,
        string $url,
        string $source,
        string $target,
        array $stepNames,
        int $position
    ) {
        $this->browser = $browser;
        $this->url = $url;
        $this->source = $source;
        $this->target = $target;
        $this->stepNames = $stepNames;
        $this->position = $position;
        $this->state = TestState::AWAITING;
    }

    public function getState(): TestState
    {
        return $this->state;
    }

    public function setState(TestState $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getSource(): string
    {
        \assert('' !== $this->source);

        return $this->source;
    }

    /**
     * @return non-empty-string
     */
    public function getTarget(): string
    {
        \assert('' !== $this->target);

        return $this->target;
    }

    /**
     * @return non-empty-string[]
     */
    public function getStepNames(): array
    {
        return $this->stepNames;
    }
}
