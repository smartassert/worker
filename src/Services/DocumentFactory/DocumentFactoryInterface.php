<?php

declare(strict_types=1);

namespace App\Services\DocumentFactory;

use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\Document;

interface DocumentFactoryInterface
{
    /**
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     */
    public function create(array $data): Document;
}
