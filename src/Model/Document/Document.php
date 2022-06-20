<?php

declare(strict_types=1);

namespace App\Model\Document;

use App\Exception\Document\InvalidDocumentException;

class Document implements DocumentInterface
{
    private const KEY_TYPE = 'type';

    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private readonly array $data
    ) {
    }

    /**
     * @throws InvalidDocumentException
     */
    public function getType(): string
    {
        $type = $this->data[self::KEY_TYPE] ?? null;
        $type = is_string($type) ? trim($type) : '';

        if ('' === $type) {
            throw new InvalidDocumentException(
                $this->data,
                'Type empty',
                InvalidDocumentException::CODE_TYPE_EMPTY
            );
        }

        return $type;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
