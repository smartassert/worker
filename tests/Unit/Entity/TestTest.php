<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use PHPUnit\Framework\TestCase;

class TestTest extends TestCase
{
    public function testHasState(): void
    {
        $test = Test::create(
            \Mockery::mock(TestConfiguration::class),
            '',
            '',
            0,
            0
        );

        self::assertTrue($test->hasState(Test::STATE_AWAITING));
        self::assertFalse($test->hasState(Test::STATE_COMPLETE));

        $test->setState(Test::STATE_COMPLETE);
        self::assertFalse($test->hasState(Test::STATE_AWAITING));
        self::assertTrue($test->hasState(Test::STATE_COMPLETE));
    }
}
