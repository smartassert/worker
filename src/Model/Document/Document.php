<?php

declare(strict_types=1);

namespace App\Model\Document;

use App\Exception\Document\InvalidDocumentException;

class Document
{
    private const KEY_TYPE = 'type';
    private const KEY_PAYLOAD = 'payload';

    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private readonly array $data
    ) {}

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
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

    public function getPayloadStringValue(string $key): ?string
    {
        $value = $this->getPayload()[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        $payload = $this->data[self::KEY_PAYLOAD] ?? [];

        return is_array($payload) ? $payload : [];
    }
}
