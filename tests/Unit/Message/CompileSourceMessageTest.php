<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\CompileSourceMessage;
use PHPUnit\Framework\TestCase;

class CompileSourceMessageTest extends TestCase
{
    private const PATH = 'Test/test.yml';

    private CompileSourceMessage $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->message = new CompileSourceMessage(self::PATH);
    }

    public function testGetPath(): void
    {
        self::assertSame(self::PATH, $this->message->path);
    }
}
