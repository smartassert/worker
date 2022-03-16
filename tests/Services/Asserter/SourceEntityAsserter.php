<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use App\Entity\Source;
use App\Services\EntityStore\SourceStore;
use App\Tests\Services\SourceFileInspector;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

class SourceEntityAsserter
{
    /**
     * @var ObjectRepository<Source>
     */
    private ObjectRepository $repository;

    public function __construct(
        EntityManagerInterface $entityManager,
        private SourceStore $sourceStore,
        private SourceFileInspector $sourceFileInspector,
    ) {
        $repository = $entityManager->getRepository(Source::class);
        \assert($repository instanceof ObjectRepository);
        $this->repository = $repository;
    }

    public function assertRepositoryIsEmpty(): void
    {
        TestCase::assertEmpty($this->repository->findAll());
    }

    /**
     * @param string[] $expected
     */
    public function assertRelativePathsEqual(array $expected): void
    {
        TestCase::assertSame($expected, $this->sourceStore->findAllPaths());
    }

    public function assertSourceExists(string $path): void
    {
        TestCase::assertTrue($this->sourceFileInspector->has($path));
    }
}
