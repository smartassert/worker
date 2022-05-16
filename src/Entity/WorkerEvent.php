<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerEventState;
use App\Enum\WorkerEventType;
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

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventType::class)]
    private WorkerEventType $type;

    #[ORM\Column(type: 'string', length: 32)]
    private string $reference;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $payload;

    /**
     * @param non-empty-string $reference
     * @param array<mixed>     $payload
     */
    public static function create(WorkerEventType $type, string $reference, array $payload): self
    {
        $entity = new WorkerEvent();
        $entity->state = WorkerEventState::AWAITING;
        $entity->type = $type;
        $entity->reference = $reference;
        $entity->payload = $payload;

        return $entity;
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

    public function getType(): WorkerEventType
    {
        return $this->type;
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
}
