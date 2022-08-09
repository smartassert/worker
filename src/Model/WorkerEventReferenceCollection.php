<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\WorkerEventReference;
use Traversable;

/**
 * @implements \IteratorAggregate<WorkerEventReference>
 */
class WorkerEventReferenceCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var WorkerEventReference[]
     */
    private array $resourceReferences = [];

    /**
     * @param WorkerEventReference[] $testReferences
     */
    public function __construct(array $testReferences)
    {
        foreach ($testReferences as $testReference) {
            if ($testReference instanceof WorkerEventReference) {
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
     * @return Traversable<WorkerEventReference>
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
