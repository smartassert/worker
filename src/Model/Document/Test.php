<?php

declare(strict_types=1);

namespace App\Model\Document;

class Test extends AbstractDocument
{
    public const KEY_PAYLOAD_PATH = 'path';

    public function getPath(): string
    {
        return (string) $this->getPayloadStringValue(self::KEY_PAYLOAD_PATH);
    }

    public function setPath(string $path): void
    {
        $this->setPayloadStringValue(self::KEY_PAYLOAD_PATH, $path);
    }
}
