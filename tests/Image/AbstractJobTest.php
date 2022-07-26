<?php

declare(strict_types=1);

namespace App\Tests\Image;

use Psr\Http\Message\ResponseInterface;

abstract class AbstractJobTest extends AbstractImageTest
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;

    protected static ResponseInterface $createResponse;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$createResponse = self::makeCreateJobRequest(array_merge(
            [
                'source' => self::createSerializedSource(static::getManifestPaths(), static::getSourcePaths()),
            ],
            static::getCreateJobParameters()
        ));
    }

    public function testMain(): void
    {
        $duration = 0;
        $durationExceeded = false;
        $waitThreshold = $this->getWaitThresholdInSeconds() * self::MICROSECONDS_PER_SECOND;

        while (false === $durationExceeded && false === $this->isApplicationToComplete()) {
            usleep(self::WAIT_INTERVAL);
            $duration += self::WAIT_INTERVAL;
            $durationExceeded = $duration >= $waitThreshold;
        }

        self::assertFalse($durationExceeded);
    }

    abstract protected function isApplicationToComplete(): bool;

    abstract protected function getWaitThresholdInSeconds(): int;

    abstract protected function doMain(): void;

    /**
     * @return string[]
     */
    abstract protected static function getManifestPaths(): array;

    /**
     * @return string[]
     */
    abstract protected static function getSourcePaths(): array;

    /**
     * @return array{label: string, event_delivery_url: string, maximum_duration_in_seconds: int}
     */
    abstract protected static function getCreateJobParameters(): array;
}
