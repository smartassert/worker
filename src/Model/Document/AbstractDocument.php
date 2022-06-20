<?php

declare(strict_types=1);

namespace App\Model\Document;

abstract class AbstractDocument
{
    public const KEY_PAYLOAD = 'payload';

    /**
     * @param array<mixed> $data
     */
    public function __construct(private array $data)
    {
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
