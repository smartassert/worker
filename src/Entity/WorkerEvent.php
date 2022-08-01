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
    private string $label;

    #[ORM\Column(type: 'string', length: 32)]
    private string $reference;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $payload;

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

    /**
     * @return array{
     *     sequence_number: int,
     *     type: string,
     *     label: non-empty-string,
     *     reference: string,
     *     payload: array<mixed>,
     *     related_references?: array<int, array{label: string, reference: string}>
     * }
     */
    public function toArray(): array
    {
        $payload = $this->payload;

        if (!$this->relatedReferences->isEmpty()) {
            $serializedRelatedReferences = [];

            foreach ($this->relatedReferences as $relatedReference) {
                if ($relatedReference instanceof ResourceReference) {
                    $serializedRelatedReferences[] = $relatedReference->toArray();
                }
            }

            $payload['related_references'] = $serializedRelatedReferences;
        }

        return [
            'sequence_number' => (int) $this->id,
            'type' => $this->scope->value . '/' . $this->outcome->value,
            'label' => $this->label,
            'reference' => $this->reference,
            'payload' => $payload,
        ];
    }
}
