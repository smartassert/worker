<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TestState;
use App\Repository\TestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestRepository::class)]
class Test implements \JsonSerializable
{
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

    /**
     * @var non-empty-string[]
     */
    #[ORM\Column(type: 'simple_array')]
    private array $stepNames = [];

    #[ORM\Column(type: 'integer', nullable: false, unique: true)]
    private int $position;

    /**
     * @param non-empty-string[] $stepNames
     */
    public function __construct(
        TestConfiguration $configuration,
        string $source,
        string $target,
        array $stepNames,
        int $position
    ) {
        $this->configuration = $configuration;
        $this->source = $source;
        $this->target = $target;
        $this->stepNames = $stepNames;
        $this->position = $position;
        $this->state = TestState::AWAITING;
    }

    /**
     * @param non-empty-string[] $stepNames
     */
    public static function create(
        TestConfiguration $configuration,
        string $source,
        string $target,
        array $stepNames,
        int $position
    ): self {
        return new Test($configuration, $source, $target, $stepNames, $position);
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

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'configuration' => $this->configuration->jsonSerialize(),
            'source' => $this->source,
            'target' => $this->target,
            'step_names' => $this->stepNames,
            'state' => $this->state->value,
            'position' => $this->position,
        ];
    }
}
