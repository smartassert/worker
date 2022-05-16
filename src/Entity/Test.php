<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestRepository::class)]
class Test implements \JsonSerializable
{
    /**
     * @var TestState[]
     */
    public const UNFINISHED_STATES = [
        TestState::AWAITING,
        TestState::RUNNING,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TestConfiguration::class)]
    #[ORM\JoinColumn(name: 'test_configuration_id', referencedColumnName: 'id', nullable: false)]
    private TestConfiguration $configuration;

    #[ORM\Column(type: 'string', length: 255, enumType: TestState::class)]
    private TestState $state;

    #[ORM\Column(type: 'text')]
    private string $source;

    #[ORM\Column(type: 'text')]
    private string $target;

    #[ORM\Column(type: 'integer')]
    private int $stepCount = 0;

    #[ORM\Column(type: 'integer', nullable: false, unique: true)]
    private int $position;

    public static function create(
        TestConfiguration $configuration,
        string $source,
        string $target,
        int $stepCount,
        int $position
    ): self {
        $test = new Test();
        $test->configuration = $configuration;
        $test->state = TestState::AWAITING;
        $test->source = $source;
        $test->target = $target;
        $test->stepCount = $stepCount;
        $test->position = $position;

        return $test;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfiguration(): TestConfiguration
    {
        return $this->configuration;
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

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function getStepCount(): int
    {
        return $this->stepCount;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'configuration' => $this->configuration->jsonSerialize(),
            'source' => $this->source,
            'target' => $this->target,
            'step_count' => $this->stepCount,
            'state' => $this->state->value,
            'position' => $this->position,
        ];
    }
}
