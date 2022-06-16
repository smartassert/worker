<?php

declare(strict_types=1);

namespace App\Tests\Functional\Messenger;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Exception\NonSuccessfulHttpResponseException;
use App\Message\DeliverEventMessage;
use App\Messenger\DeliverEventMessageRetryStrategy;
use App\Tests\AbstractBaseFunctionalTest;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class DeliverEventMessageRetryStrategyTest extends AbstractBaseFunctionalTest
{
    use MockeryPHPUnitIntegration;

    private const MAX_RETRIES = 3;

    private DeliverEventMessageRetryStrategy $retryStrategy;

    protected function setUp(): void
    {
        parent::setUp();

        $retryStrategy = self::getContainer()->get(DeliverEventMessageRetryStrategy::class);
        \assert($retryStrategy instanceof DeliverEventMessageRetryStrategy);
        $this->retryStrategy = $retryStrategy;
    }

    /**
     * @dataProvider isRetryableDataProvider
     */
    public function testIsRetryable(int $retryCount, bool $expected): void
    {
        $envelope = new Envelope(new DeliverEventMessage(0), [new RedeliveryStamp($retryCount)]);

        self::assertSame($expected, $this->retryStrategy->isRetryable($envelope));
    }

    /**
     * @return array<mixed>
     */
    public function isRetryableDataProvider(): array
    {
        return [
            'retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'expected' => true,
            ],
            'retry count is max' => [
                'retryCount' => self::MAX_RETRIES,
                'expected' => false,
            ],
            'retry count is max plus 1' => [
                'retryCount' => self::MAX_RETRIES + 1,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider getWaitingTimeDataProvider
     */
    public function testGetWaitingTime(
        int $retryCount,
        ?\Throwable $throwable,
        int $expected
    ): void {
        $envelope = new Envelope(new DeliverEventMessage(0), [new RedeliveryStamp($retryCount)]);

        self::assertSame(
            $expected,
            $this->retryStrategy->getWaitingTime($envelope, $throwable)
        );
    }

    /**
     * @return array<mixed>
     */
    public function getWaitingTimeDataProvider(): array
    {
        $workerEvent = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            md5('reference source'),
            []
        );

        return [
            'throwable is null, retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'throwable' => null,
                'expected' => 4000,
            ],
            'throwable is null, retry count is max' => [
                'retryCount' => self::MAX_RETRIES,
                'throwable' => null,
                'expected' => 8000,
            ],
            'throwable is null, retry count is max plus 1' => [
                'retryCount' => self::MAX_RETRIES + 1,
                'throwable' => null,
                'expected' => 16000,
            ],
            'throwable not an instance of NonSuccessfulHttpResponseException, retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'throwable' => new \RuntimeException(''),
                'expected' => 4000,
            ],
            'throwable has response with no retry-after header, retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'throwable' => new NonSuccessfulHttpResponseException($workerEvent, new Response()),
                'expected' => 4000,
            ],
            'throwable has response with date-based retry-after header, retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'throwable' => new NonSuccessfulHttpResponseException($workerEvent, new Response(200, [
                    'retry-after' => (new \DateTime())->format('Y-m-d H:i:s'),
                ])),
                'expected' => 4000,
            ],
            'throwable has response with int-based retry-after header 10' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'throwable' => new NonSuccessfulHttpResponseException($workerEvent, new Response(200, [
                    'retry-after' => '10',
                ])),
                'expected' => 10000,
            ],
            'throwable has response with int-based retry-after header 20' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'throwable' => new NonSuccessfulHttpResponseException($workerEvent, new Response(200, [
                    'retry-after' => '20',
                ])),
                'expected' => 20000,
            ],
        ];
    }
}
