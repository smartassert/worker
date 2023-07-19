<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Repository\WorkerEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use SmartAssert\ResultsClient\Model\EventInterface;
use SmartAssert\ResultsClient\Model\ResourceReferenceCollection;
use SmartAssert\ResultsClient\Model\ResourceReferenceCollectionInterface;
use SmartAssert\ResultsClient\Model\ResourceReferenceInterface;

/**
 * @phpstan-import-type SerializedEvent from EventInterface
 */
#[ORM\Entity(repositoryClass: WorkerEventRepository::class)]
class WorkerEvent implements \JsonSerializable, EventInterface
{
    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventScope::class)]
    public readonly WorkerEventScope $scope;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventOutcome::class)]
    public readonly WorkerEventOutcome $outcome;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: 'json')]
    public readonly array $payload;

    #[ORM\ManyToOne(targetEntity: WorkerEventReference::class)]
    #[ORM\JoinColumn(nullable: false)]
    public readonly ResourceReferenceInterface $reference;

    /**
     * @var positive-int
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventState::class)]
    private WorkerEventState $state;

    /**
     * @var Collection<int, ResourceReferenceInterface>
     */
    #[ORM\ManyToMany(targetEntity: WorkerEventReference::class, cascade: ['persist'])]
    private Collection $relatedReferences;

    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        WorkerEventScope $scope,
        WorkerEventOutcome $outcome,
        ResourceReferenceInterface $reference,
        array $payload,
    ) {
        $this->state = WorkerEventState::AWAITING;
        $this->scope = $scope;
        $this->outcome = $outcome;
        $this->reference = $reference;
        $this->payload = $payload;
        $this->relatedReferences = new ArrayCollection();
    }

    /**
     * @return positive-int
     */
    public function getId(): int
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

    /**
     * @return SerializedEvent
     */
    public function jsonSerialize(): array
    {
        $data = array_merge(
            [
                'sequence_number' => $this->getId(),
                'type' => $this->scope->value . '/' . $this->outcome->value,
                'body' => $this->payload,
            ],
            $this->reference->toArray(),
        );

        $references = [];
        foreach ($this->relatedReferences as $reference) {
            $references[] = $reference;
        }

        if (0 !== count($references)) {
            $data['related_references'] = (new ResourceReferenceCollection($references))->toArray();
        }

        return $data;
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function withRelatedReferences(ResourceReferenceCollectionInterface $relatedReferences): WorkerEvent
    {
        $this->relatedReferences = new ArrayCollection($relatedReferences->getReferences());

        return $this;
    }
}
