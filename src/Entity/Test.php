<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Test implements \JsonSerializable, EntityInterface
{
    public const STATE_AWAITING = 'awaiting';
    public const STATE_RUNNING = 'running';
    public const STATE_FAILED = 'failed';
    public const STATE_COMPLETE = 'complete';
    public const STATE_CANCELLED = 'cancelled';

    public const UNFINISHED_STATES = [
        self::STATE_AWAITING,
        self::STATE_RUNNING,
    ];

    public const FINISHED_STATES = [
        self::STATE_FAILED,
        self::STATE_COMPLETE,
        self::STATE_CANCELLED,
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\TestConfiguration")
     * @ORM\JoinColumn(name="test_configuration_id", referencedColumnName="id", nullable=false)
     */
    private TestConfiguration $configuration;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @var self::STATE_*
     */
    private string $state;

    /**
     * @ORM\Column(type="text")
     */
    private string $source;

    /**
     * @ORM\Column(type="text")
     */
    private string $target;

    /**
     * @ORM\Column(type="integer")
     */
    private int $stepCount = 0;

    /**
     * @ORM\Column(type="integer", nullable=false, unique=true)
     */
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
        $test->state = self::STATE_AWAITING;
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

    /**
     * @return self::STATE_*
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param self::STATE_* $state
     */
    public function hasState(string $state): bool
    {
        return $state === $this->state;
    }

    /**
     * @param self::STATE_* $state
     */
    public function setState(string $state): self
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
            'state' => $this->state,
            'position' => $this->position,
        ];
    }
}
