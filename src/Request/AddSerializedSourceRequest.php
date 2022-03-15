<?php

declare(strict_types=1);

namespace App\Request;

use App\Model\SerializedSource;

class AddSerializedSourceRequest
{
    public function __construct(
        private readonly SerializedSource $sourceCollection,
    ) {
    }

    public function getSourceCollection(): SerializedSource
    {
        return $this->sourceCollection;
    }
}
