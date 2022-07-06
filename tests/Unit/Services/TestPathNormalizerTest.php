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
     * @dataProvider removeCompilerSourcePrefixDataProvider
     */
    public function testRemoveCompilerSourcePrefix(string $path, string $expectedPath): void
    {
        self::assertEquals($expectedPath, $this->normalizer->removeCompilerSourcePrefix($path));
    }

    /**
     * @return array<mixed>
     */
    public function removeCompilerSourcePrefixDataProvider(): array
    {
        $relativePath = 'Test/test.yml';
        $absolutePath = self::COMPILER_SOURCE_DIRECTORY . '/' . $relativePath;

        return [
            'empty' => [
                'path' => '',
                'expectedPath' => '',
            ],
            'without prefixed path' => [
                'path' => $relativePath,
                'expectedPath' => $relativePath,
            ],
            'with source-prefixed path' => [
                'path' => $absolutePath,
                'expectedPath' => $relativePath,
            ],
        ];
    }

    /**
     * @dataProvider removeCompilerTargetPrefixDataProvider
     */
    public function testRemoveCompilerTargetPrefix(string $path, string $expectedPath): void
    {
        self::assertEquals($expectedPath, $this->normalizer->removeCompilerTargetPrefix($path));
    }

    /**
     * @return array<mixed>
     */
    public function removeCompilerTargetPrefixDataProvider(): array
    {
        $relativePath = 'GeneratedTest1234.php';
        $absolutePath = self::COMPILER_TARGET_DIRECTORY . '/' . $relativePath;

        return [
            'empty' => [
                'path' => '',
                'expectedPath' => '',
            ],
            'without prefixed path' => [
                'path' => $relativePath,
                'expectedPath' => $relativePath,
            ],
            'with target-prefixed path' => [
                'path' => $absolutePath,
                'expectedPath' => $relativePath,
            ],
        ];
    }
}
