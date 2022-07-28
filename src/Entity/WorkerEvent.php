<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Model\ResourceReferenceCollection;
use App\Repository\WorkerEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    private readonly WorkerEventScope $scope;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventOutcome::class)]
    private readonly WorkerEventOutcome $outcome;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'text')]
    private readonly string $label;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string', length: 32)]
    private readonly string $reference;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: 'json')]
    private readonly array $payload;

    /**
     * @var Collection<int, ResourceReference>
     */
    #[ORM\ManyToMany(targetEntity: ResourceReference::class, cascade: ['persist'])]
    private Collection $relatedReferences;

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
        array $payload,
        ?ResourceReferenceCollection $relatedReferences = null,
    ) {
        $this->state = WorkerEventState::AWAITING;
        $this->scope = $scope;
        $this->outcome = $outcome;
        $this->label = $label;
        $this->reference = $reference;
        $this->payload = $payload;
        $this->relatedReferences = new ArrayCollection();

        if ($relatedReferences instanceof ResourceReferenceCollection) {
            foreach ($relatedReferences as $relatedReference) {
                $this->relatedReferences->add($relatedReference);
            }
        }
    }

    public function getId(): ?int
    {
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

    public function getScope(): WorkerEventScope
    {
        return $this->scope;
    }

    public function getOutcome(): WorkerEventOutcome
    {
        return $this->outcome;
    }

    /**
     * @return non-empty-string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return non-empty-string
     */
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

    public function getRelatedReferences(): ResourceReferenceCollection
    {
        $references = [];

        foreach ($this->relatedReferences as $reference) {
            $references[] = $reference;
        }

        return new ResourceReferenceCollection($references);
    }
}
