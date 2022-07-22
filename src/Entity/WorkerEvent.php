<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Repository\WorkerEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerEventRepository::class)]
class WorkerEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventState::class)]
    private WorkerEventState $state;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventScope::class)]
    private WorkerEventScope $scope;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventOutcome::class)]
    private WorkerEventOutcome $outcome;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'text')]
    private string $label;

    #[ORM\Column(type: 'string', length: 32)]
    private string $reference;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $payload;

    /**
     * @param non-empty-string $label
     * @param non-empty-string $reference
     * @param array<mixed>     $payload
     */
    public function __construct(
        WorkerEventScope $scope,
        WorkerEventOutcome $outcome,
        string $label,
        string $reference,
        array $payload
    ) {
        $this->state = WorkerEventState::AWAITING;
        $this->scope = $scope;
        $this->outcome = $outcome;
        $this->label = $label;
        $this->reference = $reference;
        $this->payload = $payload;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getState(): WorkerEventState
    {
        return $this->state;
    }

    public function hasState(WorkerEventState $state): bool
    {
        return $state === $this->state;
    }

    public function setState(WorkerEventState $state): void
    {
        $this->state = $state;
    }

    public function getScope(): WorkerEventScope
    {
        return $this->scope;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return array{
     *     sequence_number: int,
     *     type: string,
     *     label: non-empty-string,
     *     reference: string,
     *     payload: array<mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'sequence_number' => (int) $this->id,
            'type' => $this->scope->value . '/' . $this->outcome->value,
            'label' => $this->label,
            'reference' => $this->reference,
            'payload' => $this->payload,
        ];
    }
}
