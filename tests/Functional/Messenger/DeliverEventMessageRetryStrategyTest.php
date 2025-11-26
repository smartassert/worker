<?php

declare(strict_types=1);

namespace App\Tests\Functional\Messenger;

use App\Entity\WorkerEvent;
use App\Entity\WorkerEventReference;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use App\Exception\EventDeliveryException;
use App\Message\DeliverEventMessage;
use App\Messenger\DeliverEventMessageRetryStrategy;
use App\Repository\WorkerEventReferenceRepository;
use App\Repository\WorkerEventRepository;
use GuzzleHttp\Psr7\Response;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Http\Message\ResponseInterface;
use SmartAssert\ServiceClient\Exception\NonSuccessResponseException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

class DeliverEventMessageRetryStrategyTest extends WebTestCase
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
    public static function isRetryableDataProvider(): array
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
     * @dataProvider getWaitingTimeNoThrowableDataProvider
     */
    public function testGetWaitingTimeNoThrowable(int $retryCount, int $expected): void
    {
        $envelope = new Envelope(new DeliverEventMessage(0), [new RedeliveryStamp($retryCount)]);

        self::assertSame(
            $expected,
            $this->retryStrategy->getWaitingTime($envelope)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function getWaitingTimeNoThrowableDataProvider(): array
    {
        return [
            'throwable is null, retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'expected' => 4000,
            ],
            'throwable is null, retry count is max' => [
                'retryCount' => self::MAX_RETRIES,
                'expected' => 8000,
            ],
            'throwable is null, retry count is max plus 1' => [
                'retryCount' => self::MAX_RETRIES + 1,
                'expected' => 16000,
            ],
        ];
    }

    /**
     * @dataProvider getWaitingTimeNonMatchingThrowableDataProvider
     */
    public function testGetWaitingTimeNonMatchingThrowable(
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
    public static function getWaitingTimeNonMatchingThrowableDataProvider(): array
    {
        return [
            'throwable not an instance of EventDeliveryException, retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'throwable' => new \RuntimeException(''),
                'expected' => 4000,
            ],
        ];
    }

    /**
     * @dataProvider getWaitingTimeDataProvider
     */
    public function testGetWaitingTime(
        int $retryCount,
        ResponseInterface $encapsulatedHttpResponse,
        int $expected
    ): void {
        $workerEvent = new WorkerEvent(
            WorkerEventScope::JOB,
            WorkerEventOutcome::STARTED,
            new WorkerEventReference(md5((string) rand()), md5((string) rand())),
            []
        );

        $workerEventReferenceRepository = self::getContainer()->get(WorkerEventReferenceRepository::class);
        \assert($workerEventReferenceRepository instanceof WorkerEventReferenceRepository);
        $workerEventReference = $workerEvent->reference;
        \assert($workerEventReference instanceof WorkerEventReference);
        $workerEventReferenceRepository->add($workerEventReference);

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);
        $workerEventRepository->add($workerEvent);

        $throwable = new EventDeliveryException(
            $workerEvent,
            new NonSuccessResponseException($encapsulatedHttpResponse)
        );

        $envelope = new Envelope(new DeliverEventMessage(0), [new RedeliveryStamp($retryCount)]);

        self::assertSame(
            $expected,
            $this->retryStrategy->getWaitingTime($envelope, $throwable)
        );
    }

    /**
     * @return array<mixed>
     */
    public static function getWaitingTimeDataProvider(): array
    {
        return [
            'throwable has response with no retry-after header, retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'encapsulatedHttpResponse' => new Response(),
                'expected' => 4000,
            ],
            'throwable has response with date-based retry-after header, retry count is max minus 1' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'encapsulatedHttpResponse' => new Response(200, [
                    'retry-after' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]),
                'expected' => 4000,
            ],
            'throwable has response with int-based retry-after header 10' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'encapsulatedHttpResponse' => new Response(200, [
                    'retry-after' => '10',
                ]),
                'expected' => 10000,
            ],
            'throwable has response with int-based retry-after header 20' => [
                'retryCount' => self::MAX_RETRIES - 1,
                'encapsulatedHttpResponse' => new Response(200, [
                    'retry-after' => '20',
                ]),
                'expected' => 20000,
            ],
        ];
    }
}
