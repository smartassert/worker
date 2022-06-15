<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Test;
use App\Enum\TestState;
use PHPUnit\Framework\TestCase;

class TestTest extends TestCase
{
    public function testHasState(): void
    {
        $test = new Test(
            '',
            '',
            '',
            '',
            ['step 1'],
            0
        );

        self::assertTrue($test->hasState(TestState::AWAITING));
        self::assertFalse($test->hasState(TestState::COMPLETE));

        $test->setState(TestState::COMPLETE);
        self::assertFalse($test->hasState(TestState::AWAITING));
        self::assertTrue($test->hasState(TestState::COMPLETE));
    }
}
