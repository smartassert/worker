<?php

declare(strict_types=1);

namespace App\Tests\Services\Messenger;

class NullMessageHandler
{
    public function __invoke(): void
    {
    }
}
