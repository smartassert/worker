<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Model\BackoffStrategy\BackoffStrategyInterface;
use App\Model\BackoffStrategy\ExponentialBackoffStrategy;
use App\Model\BackoffStrategy\FixedBackoffStrategy;
use App\Services\BackoffStrategyFactory;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BackoffStrategyFactoryTest extends TestCase
{
    private BackoffStrategyFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new BackoffStrategyFactory();
    }

    /**
     * @dataProvider createDataProvider
     */
    public function testCreate(object $context, BackoffStrategyInterface $expectedBackoffStrategy): void
    {
        self::assertEquals($expectedBackoffStrategy, $this->factory->create($context));
    }

    /**
     * @return array[]
     */
    public function createDataProvider(): array
    {
        return [
            'http exception' => [
                'context' => \Mockery::mock(ConnectException::class),
                'expectedBackoffStrategy' => new ExponentialBackoffStrategy(),
            ],
            'http response, no retry-after header' => [
                'context' => new Response(404),
                'expectedBackoffStrategy' => new ExponentialBackoffStrategy(),
            ],
            'http response, has single retry-after header of 10 seconds' => [
                'context' => new Response(503, [
                    'retry-after' => '10',
                ]),
                'expectedBackoffStrategy' => new FixedBackoffStrategy(10000),
            ],
            'http response, has multiple retry-after headers of 10 seconds, 20 seconds, 30 seconds' => [
                'context' => new Response(503, [
                    'retry-after' => [
                        '10',
                        '20',
                        '30',
                    ],
                ]),
                'expectedBackoffStrategy' => new FixedBackoffStrategy(30000),
            ],
            'http response, has single retry-after header with non-digit value' => [
                'context' => new Response(503, [
                    'retry-after' => 'cheese',
                ]),
                'expectedBackoffStrategy' => new ExponentialBackoffStrategy(),
            ],
            'http response, has multiple retry-after headers of 10 seconds, 20 seconds, non-digit value' => [
                'context' => new Response(503, [
                    'retry-after' => [
                        '10',
                        '20',
                        'cheese',
                    ],
                ]),
                'expectedBackoffStrategy' => new ExponentialBackoffStrategy(),
            ],
        ];
    }
}
