<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Enum\WorkerEventState;
use App\Model\WorkerEventReferenceCollection;
use App\Repository\WorkerEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerEventRepository::class)]
class WorkerEvent implements \JsonSerializable
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public readonly WorkerEventReference $reference;

    /**
     * @var positive-int
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventState::class)]
    private WorkerEventState $state;

    /**
     * @var Collection<int, WorkerEventReference>
     */
    #[ORM\ManyToMany(targetEntity: WorkerEventReference::class, cascade: ['persist'])]
    private Collection $relatedReferences;

    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        WorkerEventScope $scope,
        WorkerEventOutcome $outcome,
        WorkerEventReference $reference,
        array $payload,
        ?WorkerEventReferenceCollection $relatedReferences = null,
    ) {
        $this->state = WorkerEventState::AWAITING;
        $this->scope = $scope;
        $this->outcome = $outcome;
        $this->reference = $reference;
        $this->payload = $payload;
        $this->relatedReferences = new ArrayCollection();

        if ($relatedReferences instanceof WorkerEventReferenceCollection) {
            foreach ($relatedReferences as $relatedReference) {
                $this->relatedReferences->add($relatedReference);
            }
        }
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
     * @return array{
     *     sequence_number: int,
     *     type: non-empty-string,
     *     label: non-empty-string,
     *     reference: non-empty-string,
     *     related_references?: array<int, array{label: non-empty-string, reference: non-empty-string}>,
     *     body: array<mixed>
     * }
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
        $relatedReferences = new WorkerEventReferenceCollection($references);

        if (0 !== count($references)) {
            $data['related_references'] = $relatedReferences->toArray();
        }

        return $data;
    }
}
