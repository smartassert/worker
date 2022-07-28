<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\ResourceReference;

class ResourceReferenceCollection
{
    /**
     * @var ResourceReference[]
     */
    private array $resourceReferences = [];

    /**
     * @param ResourceReference[] $testReferences
     */
    public function __construct(array $testReferences)
    {
        foreach ($testReferences as $testReference) {
            if ($testReference instanceof ResourceReference) {
                $this->resourceReferences[] = $testReference;
            }
        }
    }

    /**
     * @return array<int, array{label: string, reference: string}>
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->resourceReferences as $testPathReference) {
            $data[] = $testPathReference->toArray();
        }

        return $data;
    }
}
