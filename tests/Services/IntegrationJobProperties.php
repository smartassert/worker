<?php

declare(strict_types=1);

namespace App\Tests\Services;

class IntegrationJobProperties
{
    private string $label;

    public function __construct()
    {
        $this->label = md5('label content');
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
