<?php

namespace App\Entity;

use App\Repository\WorkerEventReferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SmartAssert\ResultsClient\Model\ResourceReferenceInterface;

#[ORM\Entity(repositoryClass: WorkerEventReferenceRepository::class)]
class WorkerEventReference implements ResourceReferenceInterface
{
    #[ORM\Column(type: Types::TEXT)]
    private readonly string $label;

    #[ORM\Column(length: 255)]
    private readonly string $reference;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    /**
     * @param non-empty-string $label
     * @param non-empty-string $reference
     */
    public function __construct(string $label, string $reference)
    {
        $this->id = self::generateId($label, $reference);
        $this->label = $label;
        $this->reference = $reference;
    }

    /**
     * @param non-empty-string $label
     * @param non-empty-string $reference
     *
     * @return non-empty-string
     */
    public static function generateId(string $label, string $reference): string
    {
        return md5($label . ':' . $reference);
    }

    public function toArray(): array
    {
        \assert('' !== $this->label);
        \assert('' !== $this->reference);

        return [
            'label' => $this->label,
            'reference' => $this->reference,
        ];
    }
}
