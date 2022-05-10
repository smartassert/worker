<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\DeliverEventMessage;
use PHPUnit\Framework\TestCase;

class DeliverEventMessageTest extends TestCase
{
    private const ID = 9;

    private DeliverEventMessage $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->message = new DeliverEventMessage(self::ID);
    }

    public function testWorkerEventIdProperty(): void
    {
        self::assertSame(self::ID, $this->message->workerEventId);
    }
}
