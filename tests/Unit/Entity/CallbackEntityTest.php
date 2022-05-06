<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Callback\CallbackEntity;
use PHPUnit\Framework\TestCase;

class CallbackEntityTest extends TestCase
{
    public function testHasState(): void
    {
        $callback = CallbackEntity::create(
            CallbackEntity::TYPE_COMPILATION_FAILED,
            'non-empty reference',
            []
        )
        ;
        self::assertTrue($callback->hasState(CallbackEntity::STATE_AWAITING));
        self::assertFalse($callback->hasState(CallbackEntity::STATE_COMPLETE));

        $callback->setState(CallbackEntity::STATE_COMPLETE);
        self::assertFalse($callback->hasState(CallbackEntity::STATE_AWAITING));
        self::assertTrue($callback->hasState(CallbackEntity::STATE_COMPLETE));
    }
}
