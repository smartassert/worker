<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\Source;
use App\Tests\Services\EntityRemover;

class SourceTest extends AbstractEntityTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Source::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(Source::class);
        self::assertCount(0, $repository->findAll());

        $source = new Source(Source::TYPE_TEST, 'Test/test.yml');

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
