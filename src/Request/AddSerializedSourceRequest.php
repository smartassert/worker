<?php

declare(strict_types=1);

namespace App\Request;

use App\Model\SourceCollection;

class AddSerializedSourceRequest
{
    public function __construct(
        private readonly SourceCollection $sourceCollection,
    ) {
    }

    public function getSourceCollection(): SourceCollection
    {
        return $this->sourceCollection;
    }
}
