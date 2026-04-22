<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use PHPUnit\Framework\TestCase;

class ApplicationResponseDataAsserter
{
    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    public function assertJob(array $expected, array $actual): void
    {
        TestCase::assertSame($expected['label'], $actual['label']);
        TestCase::assertSame($expected['maximum_duration_in_seconds'], $actual['maximum_duration_in_seconds']);
        TestCase::assertSame($expected['sources'], $actual['sources']);

        TestCase::assertIsArray($actual['tests']);
        TestCase::assertIsArray($expected['tests']);

        foreach ($expected['tests'] as $index => $expectedTest) {
            \assert(is_array($expectedTest));
            \assert(is_string($expectedTest['browser']));

            TestCase::assertArrayHasKey($index, $actual['tests']);
            $actualTest = $actual['tests'][$index];
            TestCase::assertIsArray($actualTest);

            \assert(is_string($expectedTest['url']));
            \assert(is_string($expectedTest['source']));
            \assert(is_string($expectedTest['state']));
            \assert(is_int($expectedTest['position']));

            $expectedStepNames = $expectedTest['step_names'];
            $expectedStepNames = is_array($expectedStepNames) ? $expectedStepNames : [];
            $filteredExpectedStepNames = [];
            foreach ($expectedStepNames as $expectedStepName) {
                if (is_string($expectedStepName)) {
                    $filteredExpectedStepNames[] = $expectedStepName;
                }
            }

            $this->assertTest(
                $expectedTest['browser'],
                $expectedTest['url'],
                $expectedTest['source'],
                $filteredExpectedStepNames,
                $expectedTest['state'],
                $expectedTest['position'],
                $actualTest
            );
        }
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    public function assertApplicationState(array $expected, array $actual): void
    {
        TestCase::assertSame($expected['application'], $actual['application']);
        TestCase::assertSame($expected['compilation'], $actual['compilation']);
        TestCase::assertSame($expected['execution'], $actual['execution']);
        TestCase::assertSame($expected['event_delivery'], $actual['event_delivery']);
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
        TestCase::assertIsString($actual['target']);
        TestCase::assertMatchesRegularExpression('/^Generated[0-9a-f]{32}Test\.php$/i', $actual['target']);
        TestCase::assertSame($expectedStepNames, $actual['step_names']);
        TestCase::assertSame($expectedState, $actual['state']);
        TestCase::assertSame($expectedPosition, $actual['position']);
    }
}
