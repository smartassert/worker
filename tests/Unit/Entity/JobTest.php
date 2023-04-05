<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Job;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    private const SECONDS_PER_MINUTE = 60;

    public function testCreate(): void
    {
        $label = md5('label source');
        $eventDeliveryUrl = 'http://example.com/events';
        $resultsToken = 'results-token';
        $maximumDurationInSeconds = 10 * self::SECONDS_PER_MINUTE;

        $job = new Job($label, $eventDeliveryUrl, $resultsToken, $maximumDurationInSeconds, ['test.yml']);

        self::assertSame($label, $job->label);
        self::assertSame($eventDeliveryUrl, $job->eventDeliveryUrl);
        self::assertSame($resultsToken, $job->resultsToken);
    }
}
