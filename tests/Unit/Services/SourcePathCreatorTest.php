<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\SourcePathCreator;
use PHPUnit\Framework\TestCase;

class SourcePathCreatorTest extends TestCase
{
    private const COMPILER_SOURCE_DIRECTORY = '/app/source';

    private SourcePathCreator $sourcePathCreator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePathCreator = new SourcePathCreator(self::COMPILER_SOURCE_DIRECTORY);
    }

    /**
     * @dataProvider createAbsolutePathDataProvider
     *
     * @param non-empty-string $relativePath
     * @param non-empty-string $expected
     */
    public function testCreateAbsolutePath(string $relativePath, string $expected): void
    {
        self::assertSame($expected, $this->sourcePathCreator->createAbsolutePath($relativePath));
    }

    /**
     * @return array<string, array{relativePath: non-empty-string, expected: non-empty-string}>
     */
    public function createAbsolutePathDataProvider(): array
    {
        return [
            'relative path has path separator prefix' => [
                'relativePath' => '/Test/test.yml',
                'expected' => self::COMPILER_SOURCE_DIRECTORY . '/Test/test.yml',
            ],
            'relative path does not have path separator prefix' => [
                'relativePath' => 'Test/test.yml',
                'expected' => self::COMPILER_SOURCE_DIRECTORY . '/Test/test.yml',
            ],
            'relative path has path separator prefix and coincidental absolute path' => [
                'relativePath' => '/' . self::COMPILER_SOURCE_DIRECTORY . '/Test/test.yml',
                'expected' => self::COMPILER_SOURCE_DIRECTORY . self::COMPILER_SOURCE_DIRECTORY . '/Test/test.yml',
            ],
            'relative path does not have path separator prefix and coincidental absolute path' => [
                'relativePath' => self::COMPILER_SOURCE_DIRECTORY . '/Test/test.yml',
                'expected' => self::COMPILER_SOURCE_DIRECTORY . self::COMPILER_SOURCE_DIRECTORY . '/Test/test.yml',
            ],
        ];
    }
}
