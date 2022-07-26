<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\ApplicationState;

class JobTimeoutTest extends AbstractJobTest
{
    private const MICROSECONDS_PER_SECOND = 1000000;
    private const WAIT_INTERVAL = self::MICROSECONDS_PER_SECOND;
    private const WAIT_TIMEOUT = self::MICROSECONDS_PER_SECOND * 10;

    public function testJobTimesOut(): void
    {
        $duration = 0;
        $durationExceeded = false;

        while (false === $durationExceeded && false === $this->waitForApplicationToComplete()) {
            usleep(self::WAIT_INTERVAL);
            $duration += self::WAIT_INTERVAL;
            $durationExceeded = $duration >= self::WAIT_TIMEOUT;
        }

        self::assertFalse($durationExceeded);

        $this->assertJob(
            [
                'label' => md5('label content'),
                'event_delivery_url' => 'http://event-receiver/status/200',
                'maximum_duration_in_seconds' => 1,
                'sources' => [
                    'Test/chrome-open-index.yml',
                    'Test/firefox-open-index.yml',
                    'Test/chrome-firefox-open-index.yml',
                    'Test/chrome-open-form.yml',
                    'Page/index.yml',
                ],
                'tests' => [
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'complete',
                        'position' => 1,
                    ],
                    [
                        'browser' => 'firefox',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/firefox-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'cancelled',
                        'position' => 2,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'cancelled',
                        'position' => 3,
                    ],
                    [
                        'browser' => 'firefox',
                        'url' => 'http://html-fixtures/index.html',
                        'source' => 'Test/chrome-firefox-open-index.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'cancelled',
                        'position' => 4,
                    ],
                    [
                        'browser' => 'chrome',
                        'url' => 'http://html-fixtures/form.html',
                        'source' => 'Test/chrome-open-form.yml',
                        'step_names' => [
                            'verify page is open',
                        ],
                        'state' => 'cancelled',
                        'position' => 5,
                    ],
                ],
            ],
            $this->fetchJob()
        );
        $this->assertApplicationState(
            [
                'application' => 'timed-out',
                'compilation' => 'complete',
                'execution' => 'cancelled',
                'event_delivery' => 'complete',
            ],
            $this->fetchApplicationState()
        );
    }

    protected static function getManifestPaths(): array
    {
        return [
            'Test/chrome-open-index.yml',
            'Test/firefox-open-index.yml',
            'Test/chrome-firefox-open-index.yml',
            'Test/chrome-open-form.yml',
        ];
    }

    protected static function getSourcePaths(): array
    {
        return [
            'Test/chrome-open-index.yml',
            'Test/firefox-open-index.yml',
            'Test/chrome-firefox-open-index.yml',
            'Test/chrome-open-form.yml',
            'Page/index.yml',
        ];
    }

    protected static function getCreateJobParameters(): array
    {
        return [
            'label' => md5('label content'),
            'event_delivery_url' => 'http://event-receiver/status/200',
            'maximum_duration_in_seconds' => 1,
        ];
    }

    private function waitForApplicationToComplete(): bool
    {
        $state = $this->fetchApplicationState();

        return ApplicationState::TIMED_OUT->value === $state['application'];
    }
}
