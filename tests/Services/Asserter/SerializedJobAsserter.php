<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use PHPUnit\Framework\TestCase;

class SerializedJobAsserter
{
    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    public function assertJob(array $expected, array $actual): void
    {
        TestCase::assertIsArray($actual);

        TestCase::assertSame($expected['label'], $actual['label']);
        TestCase::assertSame($expected['event_delivery_url'], $actual['event_delivery_url']);
        TestCase::assertSame($expected['maximum_duration_in_seconds'], $actual['maximum_duration_in_seconds']);
        TestCase::assertSame($expected['sources'], $actual['sources']);
        TestCase::assertSame($expected['application_state'], $actual['application_state']);
        TestCase::assertSame($expected['compilation_state'], $actual['compilation_state']);
        TestCase::assertSame($expected['execution_state'], $actual['execution_state']);
        TestCase::assertSame($expected['event_delivery_state'], $actual['event_delivery_state']);

        TestCase::assertIsArray($actual['tests']);
        TestCase::assertIsArray($expected['tests']);

        foreach ($expected['tests'] as $index => $expectedTest) {
            TestCase::assertArrayHasKey($index, $actual['tests']);
            $actualTest = $actual['tests'][$index];
            TestCase::assertIsArray($actualTest);

            $this->assertTest(
                $expectedTest['browser'],
                $expectedTest['url'],
                $expectedTest['source'],
                $expectedTest['step_names'],
                $expectedTest['state'],
                $expectedTest['position'],
                $actualTest
            );
        }
    }

    /**
     * @param array<mixed> $actual
     * @param string[]     $expectedStepNames
     */
    private function assertTest(
        string $expectedBrowser,
        string $expectedUrl,
        string $expectedSource,
        array $expectedStepNames,
        string $expectedState,
        int $expectedPosition,
        array $actual
    ): void {
        TestCase::assertSame($expectedBrowser, $actual['browser']);
        TestCase::assertSame($expectedUrl, $actual['url']);

        TestCase::assertSame($expectedSource, $actual['source']);
        TestCase::assertArrayHasKey('target', $actual);
        TestCase::assertMatchesRegularExpression('/^Generated[0-9a-f]{32}Test\.php$/i', $actual['target']);
        TestCase::assertSame($expectedStepNames, $actual['step_names']);
        TestCase::assertSame($expectedState, $actual['state']);
        TestCase::assertSame($expectedPosition, $actual['position']);
    }
}
