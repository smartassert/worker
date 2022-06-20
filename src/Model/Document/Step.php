<?php

declare(strict_types=1);

namespace App\Model\Document;

use App\Exception\Document\InvalidDocumentException;

class Step extends AbstractDocument
{
    private const KEY_PAYLOAD_STATUS = 'status';
    private const TYPE = 'step';
    private const STATUS_PASSED = 'passed';
    private const STATUS_FAILED = 'failed';
    private const KEY_PAYLOAD_NAME = 'name';

    /**
     * @throws InvalidDocumentException
     */
    public function isStep(): bool
    {
        return self::TYPE === $this->getType();
    }

    public function statusIsPassed(): bool
    {
        return $this->hasStatusValue(self::STATUS_PASSED);
    }

    public function statusIsFailed(): bool
    {
        return $this->hasStatusValue(self::STATUS_FAILED);
    }

    public function getName(): ?string
    {
        return $this->getPayloadStringValue(self::KEY_PAYLOAD_NAME);
    }

    private function hasStatusValue(string $status): bool
    {
        return $status === $this->getPayloadStringValue(self::KEY_PAYLOAD_STATUS);
    }
}
