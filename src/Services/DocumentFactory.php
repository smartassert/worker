<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\Document\InvalidDocumentException;
use App\Model\Document\DocumentInterface;
use App\Model\Document\Step;

class DocumentFactory
{
    /**
     * @param array<mixed> $data
     *
     * @throws InvalidDocumentException
     */
    public function create(array $data): DocumentInterface
    {
        $type = $data['type'] ?? null;
        $type = is_string($type) ? trim($type) : null;

        if ('step' !== $type) {
            throw new InvalidDocumentException(
                $data,
                sprintf(
                    'Type "%s" is not one of "%s"',
                    (string) $type,
                    implode(', ', ['step'])
                ),
                InvalidDocumentException::CODE_TYPE_INVALID
            );
        }

        return new Step($data);
    }
}
