<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Exception\NonSuccessfulHttpResponseException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

class DeliverEventMessageRetryStrategy implements RetryStrategyInterface
{
    private const MILLISECONDS_PER_SECOND = 1000;

    public function __construct(
        private readonly MultiplierRetryStrategy $multiplierRetryStrategy,
    ) {
    }

    public function isRetryable(Envelope $message, \Throwable $throwable = null): bool
    {
        return $this->multiplierRetryStrategy->isRetryable($message, $throwable);
    }

    public function getWaitingTime(Envelope $message, \Throwable $throwable = null): int
    {
        if ($throwable instanceof NonSuccessfulHttpResponseException) {
            $response = $throwable->getResponse();

            $retryAfterHeaderLines = $response->getHeader('retry-after');
            $lastRetryAfterValue = array_pop($retryAfterHeaderLines);
            $lastRetryAfterValue = is_scalar($lastRetryAfterValue) ? (string) $lastRetryAfterValue : '';

            $retryAfterSeconds = ctype_digit($lastRetryAfterValue) ? (int) $lastRetryAfterValue : null;

            if ($retryAfterSeconds > 0) {
                return $retryAfterSeconds * self::MILLISECONDS_PER_SECOND;
            }
        }

        return $this->multiplierRetryStrategy->getWaitingTime($message, $throwable);
    }
}
