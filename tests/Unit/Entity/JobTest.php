<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Job;
use PHPUnit\Framework\TestCase;
use webignition\ObjectReflector\ObjectReflector;

class JobTest extends TestCase
{
    private const SECONDS_PER_MINUTE = 60;

    public function testCreate(): void
    {
        $label = md5('label source');
        $callbackUrl = 'http://example.com/callback';
        $maximumDurationInSeconds = 10 * self::SECONDS_PER_MINUTE;

        $job = Job::create($label, $callbackUrl, $maximumDurationInSeconds);

        self::assertSame(1, $job->getId());
        self::assertSame($label, $job->getLabel());
        self::assertSame($callbackUrl, $job->getCallbackUrl());
    }

    /**
     * @dataProvider jsonSerializeDataProvider
     *
     * @param array<mixed> $expectedSerializedJob
     */
    public function testJsonSerialize(Job $job, array $expectedSerializedJob): void
    {
        self::assertSame($expectedSerializedJob, $job->jsonSerialize());
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerializeDataProvider(): array
    {
        return [
            'state compilation-awaiting' => [
                'job' => Job::create('label content', 'http://example.com/callback', 1),
                'expectedSerializedJob' => [
                    'label' => 'label content',
                    'callback_url' => 'http://example.com/callback',
                    'maximum_duration_in_seconds' => 1,
                ],
            ],
        ];
    }

    /**
     * @dataProvider hasReachedMaximumDurationDataProvider
     */
    public function testHasReachedMaximumDuration(Job $job, bool $hasReachedMaximumDuration): void
    {
        self::assertSame($hasReachedMaximumDuration, $job->hasReachedMaximumDuration());
    }

    /**
     * @return array<mixed>
     */
    public function hasReachedMaximumDurationDataProvider(): array
    {
        $maximumDuration = 10 * self::SECONDS_PER_MINUTE;

        return [
            'start date time not set' => [
                'job' => Job::create('', '', $maximumDuration),
                'expectedHasReachedMaximumDuration' => false,
            ],
            'not exceeded: start date time is now' => [
                'job' => (function () use ($maximumDuration) {
                    $job = Job::create('', '', $maximumDuration);
                    $job->setStartDateTime();

                    return $job;
                })(),
                'expectedHasReachedMaximumDuration' => false,
            ],
            'not exceeded: start date time is less than max duration seconds ago' => [
                'job' => (function () use ($maximumDuration) {
                    $job = Job::create('', '', $maximumDuration);
                    $startDateTime = new \DateTimeImmutable('-9 minute -50 second');

                    ObjectReflector::setProperty($job, Job::class, 'startDateTime', $startDateTime);

                    return $job;
                })(),
                'expectedHasReachedMaximumDuration' => false,
            ],
            'exceeded: start date time is max duration minutes ago' => [
                'job' => (function () use ($maximumDuration) {
                    $job = Job::create('', '', $maximumDuration);
                    $startDateTime = new \DateTimeImmutable('-10 minute');

                    ObjectReflector::setProperty($job, Job::class, 'startDateTime', $startDateTime);

                    return $job;
                })(),
                'expectedHasReachedMaximumDuration' => true,
            ],
            'exceeded: start date time is greater than max duration minutes ago' => [
                'job' => (function () use ($maximumDuration) {
                    $job = Job::create('', '', $maximumDuration);
                    $startDateTime = new \DateTimeImmutable('-10 minute -1 second');

                    ObjectReflector::setProperty($job, Job::class, 'startDateTime', $startDateTime);

                    return $job;
                })(),
                'expectedHasReachedMaximumDuration' => true,
            ],
        ];
    }
}
