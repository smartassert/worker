<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\DeliverEventMessage;
use PHPUnit\Framework\TestCase;

class SendCallbackMessageTest extends TestCase
{
    private const CALLBACK_ID = 9;

    private DeliverEventMessage $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->message = new DeliverEventMessage(self::CALLBACK_ID);
    }

    public function testGetCallbackId(): void
    {
        self::assertSame(self::CALLBACK_ID, $this->message->getCallbackId());
    }
}
