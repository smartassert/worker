<?php

declare(strict_types=1);

namespace App\Tests\Services;

class IntegrationJobProperties
{
    private string $label;

    public function __construct(private string $baseUrl)
    {
        $this->label = md5('label content');
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getEventDeliveryUrl(): string
    {
        return $this->createEventDeliveryUrlForStatusCode(200);
    }

    public function createEventDeliveryUrlForStatusCode(int $statusCode): string
    {
        return $this->baseUrl . '/status/' . (string) $statusCode;
    }
}
