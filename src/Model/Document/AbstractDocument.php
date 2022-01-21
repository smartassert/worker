<?php

declare(strict_types=1);

namespace App\Model\Document;

use webignition\YamlDocument\Document;

abstract class AbstractDocument
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

    public function getType(): ?string
    {
        $type = $this->data[self::KEY_TYPE] ?? null;

        return is_string($type) ? $type : null;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<mixed> $mutations
     *
     * @return array<mixed>
     */
    public function getMutatedData(array $mutations): array
    {
        return array_merge($this->data, $mutations);
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
}
