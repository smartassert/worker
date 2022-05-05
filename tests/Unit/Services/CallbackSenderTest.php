<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use App\Services\CallbackSender;
use App\Tests\Mock\Repository\MockJobRepository;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

class CallbackSenderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testSendNoJob(): void
    {
        $httpClient = \Mockery::mock(ClientInterface::class);

        $jobRepository = (new MockJobRepository())
            ->withGetCall(null)
            ->getMock()
        ;

        $callbackSender = new CallbackSender($httpClient, $jobRepository);
        $callbackSender->send(CallbackEntity::create(
            CallbackInterface::TYPE_JOB_STARTED,
            'non-empty reference',
            []
        ));
    }
}
