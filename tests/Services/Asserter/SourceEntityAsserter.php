<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use App\Repository\SourceRepository;
use App\Services\EntityStore\SourceStore;
use PHPUnit\Framework\TestCase;

class SourceEntityAsserter
{
    public function __construct(
        private SourceRepository $sourceRepository,
        private SourceStore $sourceStore,
    ) {
    }

    public function assertRepositoryIsEmpty(): void
    {
        TestCase::assertEmpty($this->sourceRepository->findAll());
    }

    /**
     * @param string[] $expected
     */
    public function assertRelativePathsEqual(array $expected): void
    {
        TestCase::assertSame($expected, $this->sourceStore->findAllPaths());
    }
}
