<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerEventState;
use App\Repository\WorkerEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerEventRepository::class)]
class WorkerEvent
{
    #[ORM\Column(type: 'string', length: 255)]
    public readonly string $type;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: 'json')]
    public readonly array $payload;

    #[ORM\ManyToOne(targetEntity: WorkerEventReference::class)]
    #[ORM\JoinColumn(nullable: false)]
    public readonly WorkerEventReference $reference;

    /**
     * @var Collection<int, WorkerEventReference>
     */
    #[ORM\ManyToMany(targetEntity: WorkerEventReference::class, cascade: ['persist'])]
    private Collection $relatedReferences;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventState::class)]
    private WorkerEventState $state;

    /**
     * @param non-empty-string            $type
     * @param array<mixed>                $payload
     * @param null|WorkerEventReference[] $relatedReferences
     */
    public function __construct(
        string $type,
        WorkerEventReference $reference,
        array $payload,
        ?array $relatedReferences = null,
    ) {
        $this->state = WorkerEventState::QUEUED;
        $this->type = $type;
        $this->reference = $reference;
        $this->payload = $payload;

        $this->relatedReferences = is_array($relatedReferences)
            ? new ArrayCollection($relatedReferences)
            : new ArrayCollection();
    }

    /**
     * @return positive-int
     */
    public function getId(): int
    {
        \assert($this->id > 0);

        return $this->id;
    }

    public function getState(): WorkerEventState
    {
        return $this->state;
    }

    public function setState(WorkerEventState $state): void
    {
        $this->state = $state;
    }

    /**
     * @return non-empty-string
     */
    public function getType(): string
    {
        \assert('' !== $this->type);

        return $this->type;
    }

    /**
     * @return Collection<int, WorkerEventReference>
     */
    public function getRelatedReferences(): Collection
    {
        return $this->relatedReferences;
    }
}
