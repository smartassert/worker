<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TestState;
use App\Repository\TestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestRepository::class)]
class Test
{
    #[ORM\Column(type: 'string', length: 255)]
    public readonly string $browser;

    #[ORM\Column(type: 'string', length: 255)]
    public readonly string $url;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'text')]
    public readonly string $source;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'text')]
    public readonly string $target;

    /**
     * @var non-empty-string[]
     */
    #[ORM\Column(type: 'simple_array')]
    public readonly array $stepNames;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, enumType: TestState::class)]
    private TestState $state;

    #[ORM\Column(type: 'integer', nullable: false, unique: true)]
    private int $position;

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

    public function getPosition(): int
    {
        return $this->position;
    }
}
