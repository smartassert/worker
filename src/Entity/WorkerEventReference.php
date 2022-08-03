<?php

namespace App\Entity;

use App\Repository\WorkerEventReferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerEventReferenceRepository::class)]
#[ORM\UniqueConstraint(name: 'resource_reference_unique', columns: ['label', 'reference'])]
class WorkerEventReference
{
    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: Types::TEXT)]
    private readonly string $label;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 255)]
    private readonly string $reference;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private int $id;

    /**
     * @param non-empty-string $label
     * @param non-empty-string $reference
     */
    public function __construct(string $label, string $reference)
    {
        $this->label = $label;
        $this->reference = $reference;
    }

    /**
     * @return array{label: non-empty-string, reference: non-empty-string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'reference' => $this->reference,
        ];
    }
}
