<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\TestPathMutator;
use PHPUnit\Framework\TestCase;

class TestPathMutatorTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';

    private TestPathMutator $mutator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mutator = new TestPathMutator(self::COMPILER_SOURCE_DIRECTORY);
    }

    /**
     * @dataProvider removeCompilerSourceDirectoryFromPathDataProvider
     */
    public function testRemoveCompilerSourceDirectoryFromPath(string $path, string $expectedPath): void
    {
        self::assertEquals($expectedPath, $this->mutator->removeCompilerSourceDirectoryFromPath($path));
    }

    /**
     * @return array<mixed>
     */
    public function removeCompilerSourceDirectoryFromPathDataProvider(): array
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
