<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\TestPathNormalizer;
use PHPUnit\Framework\TestCase;

class TestPathNormalizerTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';

    private TestPathNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new TestPathNormalizer(self::COMPILER_SOURCE_DIRECTORY);
    }

    /**
     * @dataProvider normalizeDataProvider
     */
    public function testNormalize(string $path, string $expectedPath): void
    {
        self::assertEquals($expectedPath, $this->normalizer->normalize($path));
    }

    /**
     * @return array<mixed>
     */
    public function normalizeDataProvider(): array
    {
        $relativePath = 'Test/test.yml';
        $compilerSourceAbsolutePath = self::COMPILER_SOURCE_DIRECTORY . '/' . $relativePath;

        return [
            'without prefixed path' => [
                'path' => $relativePath,
                'expectedPath' => $relativePath,
            ],
            'with prefixed path' => [
                'path' => $compilerSourceAbsolutePath,
                'expectedPath' => $relativePath,
            ],
        ];
    }
}
