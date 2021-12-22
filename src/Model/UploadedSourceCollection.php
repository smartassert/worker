<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @implements \ArrayAccess<string, UploadedSource>
 * @implements \IteratorAggregate<string, UploadedSource>
 */
class UploadedSourceCollection implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var UploadedSource[]
     */
    private array $uploadedSources = [];

    /**
     * @param array<mixed> $uploadedSources
     */
    public function __construct(array $uploadedSources = [])
    {
        foreach ($uploadedSources as $uploadedSource) {
            if ($uploadedSource instanceof UploadedSource) {
                $this->uploadedSources[$uploadedSource->getPath()] = $uploadedSource;
            }
        }
    }

    /**
     * @return \Iterator<string, UploadedSource>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->uploadedSources);
    }

    public function contains(string $path): bool
    {
        return $this->offsetExists($path);
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->uploadedSources);
    }

    public function offsetGet($offset): ?UploadedSource
    {
        return $this->uploadedSources[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($value instanceof UploadedSource) {
            $this->uploadedSources[$value->getPath()] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->uploadedSources[$offset]);
    }
}
