<?php

declare(strict_types=1);

namespace App\Model\Document;

class Test extends Document
{
    /**
     * @param non-empty-string $path
     */
    public function __construct(
        private readonly string $path,
        array $data
    ) {
        parent::__construct($data);
    }

    /**
     * @return non-empty-string
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
