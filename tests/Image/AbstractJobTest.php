<?php

declare(strict_types=1);

namespace App\Tests\Image;

use Psr\Http\Message\ResponseInterface;

abstract class AbstractJobTest extends AbstractImageTest
{
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
