<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\EntityInterface;
use App\Tests\AbstractBaseFunctionalTest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractEntityRepositoryTest extends AbstractBaseFunctionalTest
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
    }

//    public function testFind(): void
//    {
//        $this->assertNull($this->repository->find(0));
//
//        $entity = $this->createSingleEntity();
//        self::assertInstanceOf(EntityInterface::class, $entity);
//
//        $this->persistEntity($entity);
//
//        self::assertIsInt($entity->getId());
//        self::assertSame($entity, $this->repository->find($entity->getId()));
//    }
//
//    public function testFindAll(): void
//    {
//        $this->assertSame([], $this->repository->findAll());
//
//        $entities = $this->createEntityCollection();
//        foreach ($entities as $entity) {
//            $this->persistEntity($entity);
//        }
//
//        self::assertSame($entities, $this->repository->findAll());
//    }
//
//    /**
//     * @dataProvider findOneByDataProvider
//     *
//     * @param mixed[]                    $criteria
//     * @param null|array<string, string> $orderBy
//     */
//    public function testFindOneBy(array $criteria, ?array $orderBy, ?int $expectedEntityIndex): void
//    {
//        $entities = $this->createEntityCollection();
//        foreach ($entities as $entity) {
//            $this->persistEntity($entity);
//        }
//
//        $entity = $this->repository->findOneBy($criteria, $orderBy);
//
//        if (null === $expectedEntityIndex) {
//            self::assertNull($entity);
//        } else {
//            self::assertSame($entities[$expectedEntityIndex], $entity);
//        }
//    }
//
//    /**
//     * @dataProvider countDataProvider
//     *
//     * @param mixed[] $criteria
//     */
//    public function testCount(array $criteria, int $expectedCount): void
//    {
//        $entities = $this->createEntityCollection();
//        foreach ($entities as $entity) {
//            $this->persistEntity($entity);
//        }
//
//        self::assertSame($expectedCount, $this->repository->count($criteria));
//    }
//
//    public function testCreateQueryBuilder(): void
//    {
//        $queryBuilder = $this->repository->createQueryBuilder('Foo');
//
//        self::assertInstanceOf(QueryBuilder::class, $queryBuilder);
//        self::assertSame($this->entityManager, $queryBuilder->getEntityManager());
//    }
//
//    /**
//     * @return null|ServiceEntityRepository<EntityInterface>
//     */
//    abstract protected function getRepository(): ?ServiceEntityRepository;
//
//    abstract protected function createSingleEntity(): EntityInterface;
//
//    /**
//     * @return array<mixed>
//     */
//    abstract protected function findOneByDataProvider(): array;
//
//    /**
//     * @return array<mixed>
//     */
//    abstract protected function countDataProvider(): array;
//
//    /**
//     * @return EntityInterface[]
//     */
//    abstract protected function createEntityCollection(): array;

    protected function persistEntity(EntityInterface $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
