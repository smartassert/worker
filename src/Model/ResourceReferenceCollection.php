<?php

declare(strict_types=1);

namespace App\Model;

class ResourceReferenceCollection implements \JsonSerializable
{
    /**
     * @var ResourceReference[]
     */
    private array $testPathReferences = [];

    /**
     * @param ResourceReference[] $testReferences
     */
    public function __construct(array $testReferences)
    {
        foreach ($testReferences as $testReference) {
            if ($testReference instanceof ResourceReference) {
                $this->testPathReferences[] = $testReference;
            }
        }
    }

    /**
     * @return ResourceReference[]
     */
    public function jsonSerialize(): array
    {
        return $this->testPathReferences;
    }
}
