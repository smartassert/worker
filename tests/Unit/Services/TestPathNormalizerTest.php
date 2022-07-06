<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\TestPathNormalizer;
use PHPUnit\Framework\TestCase;

class TestPathNormalizerTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';
    private const COMPILER_TARGET_DIRECTORY = '/app/target';

    private TestPathNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new TestPathNormalizer(
            self::COMPILER_SOURCE_DIRECTORY,
            self::COMPILER_TARGET_DIRECTORY
        );
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
        $sourceRelativePath = 'Test/test.yml';
        $targetRelativePath = 'GeneratedTest1234.php';

        $compilerSourceAbsolutePath = self::COMPILER_SOURCE_DIRECTORY . '/' . $sourceRelativePath;
        $compilerTargetAbsolutePath = self::COMPILER_TARGET_DIRECTORY . '/' . $targetRelativePath;

        return [
            'empty' => [
                'path' => '',
                'expectedPath' => '',
            ],
            'without prefixed path' => [
                'path' => $sourceRelativePath,
                'expectedPath' => $sourceRelativePath,
            ],
            'with source-prefixed path' => [
                'path' => $compilerSourceAbsolutePath,
                'expectedPath' => $sourceRelativePath,
            ],
            'with target-prefixed path' => [
                'path' => $compilerTargetAbsolutePath,
                'expectedPath' => $targetRelativePath,
            ],
            'with source-prefixed and target-prefixed path' => [
                'path' => self::COMPILER_SOURCE_DIRECTORY . '/' . self::COMPILER_TARGET_DIRECTORY . $sourceRelativePath,
                'expectedPath' => self::COMPILER_TARGET_DIRECTORY . $sourceRelativePath,
            ],
        ];
    }
}
