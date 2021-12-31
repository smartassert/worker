<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\CallbackSender;
use App\Tests\Mock\Services\MockJobStore;
use App\Tests\Model\TestCallback;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

class CallbackSenderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testSendNoJob(): void
    {
        $httpClient = \Mockery::mock(ClientInterface::class);

        $jobStore = (new MockJobStore())
            ->withHasCall(false)
            ->getMock()
        ;

        $callbackSender = new CallbackSender($httpClient, $jobStore);

        $callbackSender->send(new TestCallback());
    }
}
