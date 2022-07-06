<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TestState;
use App\Repository\TestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestRepository::class)]
class Test
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $browser;

    #[ORM\Column(type: 'string', length: 255)]
    private string $url;

    #[ORM\Column(type: 'string', length: 255, enumType: TestState::class)]
    private TestState $state;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'text')]
    private string $source;

    #[ORM\Column(type: 'text')]
    private string $target;

    /**
     * @var non-empty-string[]
     */
    #[ORM\Column(type: 'simple_array')]
    private array $stepNames = [];

    #[ORM\Column(type: 'integer', nullable: false, unique: true)]
    private int $position;

    /**
     * @param non-empty-string   $source
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrowser(): string
    {
        return $this->browser;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getState(): TestState
    {
        return $this->state;
    }

    public function hasState(TestState $state): bool
    {
        return $state === $this->state;
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
        return $this->source;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    /**
     * @return non-empty-string[]
     */
    public function getStepNames(): array
    {
        return $this->stepNames;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
