<?php

declare(strict_types=1);

namespace App\Model\Document;

class Step extends Document
{
    private const KEY_PAYLOAD_STATUS = 'status';
    private const STATUS_PASSED = 'passed';
    private const STATUS_FAILED = 'failed';

    /**
     * @param non-empty-string $name
     */
    public function __construct(
        private readonly string $name,
        array $data
    ) {
        parent::__construct($data);
    }

    public function statusIsPassed(): bool
    {
        return $this->hasStatusValue(self::STATUS_PASSED);
    }

    public function statusIsFailed(): bool
    {
        return $this->hasStatusValue(self::STATUS_FAILED);
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    private function hasStatusValue(string $status): bool
    {
        return $status === $this->getPayloadStringValue(self::KEY_PAYLOAD_STATUS);
    }
}
