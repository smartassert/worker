<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\ResourceReference;
use Traversable;

/**
 * @implements \IteratorAggregate<ResourceReference>
 */
class ResourceReferenceCollection implements \IteratorAggregate, \Countable
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
     * @return array<int, array{label: non-empty-string, reference: non-empty-string}>
     */
    public function toArray(): array
    {
        $data = [];
        foreach ($this->resourceReferences as $testPathReference) {
            $data[] = $testPathReference->toArray();
        }

        return $data;
    }

    /**
     * @return Traversable<ResourceReference>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->resourceReferences);
    }

    public function count(): int
    {
        return count($this->resourceReferences);
    }
}
