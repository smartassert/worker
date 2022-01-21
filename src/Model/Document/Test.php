<?php

declare(strict_types=1);

namespace App\Model\Document;

class Test extends AbstractDocument
{
    public const KEY_PAYLOAD_PATH = 'path';
    public const KEY_PAYLOAD = 'payload';
    private const TYPE = 'test';

    public function isTest(): bool
    {
        return self::TYPE === $this->getType();
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        $payload = $this->getData()[self::KEY_PAYLOAD] ?? [];

        return is_array($payload) ? $payload : [];
    }

    public function getPath(): string
    {
        $path = $this->getPayload()[self::KEY_PAYLOAD_PATH] ?? '';

        return is_string($path) ? $path : '';
    }
}
