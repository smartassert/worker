<?php

declare(strict_types=1);

namespace App\Model\Document;

class Test extends AbstractDocument
{
    private const TYPE = 'test';

    public function __construct(
        private readonly string $path,
        array $data
    ) {
        parent::__construct($data);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
