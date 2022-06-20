<?php

declare(strict_types=1);

namespace App\Model\Document;

use App\Exception\Document\InvalidDocumentException;
use webignition\YamlDocument\Document;

abstract class AbstractDocument implements DocumentInterface
{
    public const KEY_PAYLOAD = 'payload';
    private const KEY_TYPE = 'type';

    /**
     * @var array<mixed>
     */
    private array $data;

    public function __construct(Document $document)
    {
        $data = $document->parse();
        $this->data = is_array($data) ? $data : [];
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

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        $payload = $this->getData()[self::KEY_PAYLOAD] ?? [];

        return is_array($payload) ? $payload : [];
    }

    protected function getPayloadStringValue(string $key): ?string
    {
        $value = $this->getPayload()[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    protected function setPayloadStringValue(string $key, string $value): void
    {
        $payload = $this->getPayload();

        if (array_key_exists($key, $payload)) {
            $payload[$key] = $value;

            $this->data[self::KEY_PAYLOAD] = $payload;
        }
    }
}
