<?php

namespace App\Entity;

use App\Repository\ResourceReferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResourceReferenceRepository::class)]
#[ORM\UniqueConstraint(name: 'resource_reference_unique', columns: ['label', 'reference'])]
class ResourceReference
{
    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: Types::TEXT)]
    public readonly string $label;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(length: 255)]
    public readonly string $reference;

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
     * @return array{label: string, reference: string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'reference' => $this->reference,
        ];
    }
}
