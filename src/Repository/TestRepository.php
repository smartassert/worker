<?php

namespace App\Repository;

use App\Entity\Test;
use App\Entity\TestConfiguration;
use App\Entity\TestState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|Test find($id, $lockMode = null, $lockVersion = null)
 * @method null|Test findOneBy(array $criteria, array $orderBy = null)
 * @method Test[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<Test>
 */
class TestRepository extends ServiceEntityRepository
{
    public const DEFAULT_MAX_POSITION = 0;

    public function __construct(
        ManagerRegistry $registry,
        private readonly TestConfigurationRepository $configurationRepository,
    ) {
        parent::__construct($registry, Test::class);
    }

    public function add(Test $test): Test
    {
        $this->_em->persist($test);
        $this->_em->flush();

        return $test;
    }

    public function create(
        TestConfiguration $configuration,
        string $source,
        string $target,
        int $stepCount
    ): Test {
        return $this->add(Test::create(
            $this->configurationRepository->get($configuration),
            $source,
            $target,
            $stepCount,
            $this->findMaxPosition() + 1
        ));
    }

    /**
     * @return Test[]
     */
    public function findAll(): array
    {
        return $this->findBy([], [
            'position' => 'ASC',
        ]);
    }

    public function findMaxPosition(): int
    {
        $queryBuilder = $this->createQueryBuilder('Test');
        $queryBuilder
            ->select('Test.position')
            ->orderBy('Test.position', 'DESC')
            ->setMaxResults(1)
        ;

        $query = $queryBuilder->getQuery();
        $value = $this->getSingleIntegerResult($query);

        return is_int($value) ? $value : self::DEFAULT_MAX_POSITION;
    }

    public function findNextAwaitingId(): ?int
    {
        $queryBuilder = $this->createQueryBuilder('Test');

        $queryBuilder
            ->select('Test.id')
            ->where('Test.state = :State')
            ->orderBy('Test.position', 'ASC')
            ->setMaxResults(1)
            ->setParameter('State', TestState::AWAITING->value)
        ;

        $query = $queryBuilder->getQuery();

        return $this->getSingleIntegerResult($query);
    }

    /**
     * @return Test[]
     */
    public function findAllAwaiting(): array
    {
        return $this->findBy([
            'state' => TestState::AWAITING->value,
        ]);
    }

    /**
     * @return Test[]
     */
    public function findAllUnfinished(): array
    {
        $unfinishedStateValues = [];
        foreach (Test::UNFINISHED_STATES as $state) {
            $unfinishedStateValues[] = $state->value;
        }

        return $this->findBy([
            'state' => $unfinishedStateValues,
        ]);
    }

    /**
     * @return string[]
     */
    public function findAllSources(): array
    {
        $queryBuilder = $this->createQueryBuilder('Test');
        $queryBuilder
            ->select('Test.source')
        ;

        $query = $queryBuilder->getQuery();

        $result = $query->getArrayResult();

        $sources = [];
        foreach ($result as $item) {
            if (is_array($item)) {
                $sources[] = (string) ($item['source'] ?? null);
            }
        }

        return $sources;
    }

    public function findUnfinishedCount(): int
    {
        $unfinishedStateValues = [];
        foreach (Test::UNFINISHED_STATES as $state) {
            $unfinishedStateValues[] = $state->value;
        }

        $queryBuilder = $this->createQueryBuilder('Test');
        $queryBuilder
            ->select('count(Test.id)')
            ->where('Test.state IN (:States)')
            ->setParameter('States', $unfinishedStateValues)
        ;

        $query = $queryBuilder->getQuery();
        $value = $this->getSingleIntegerResult($query);

        return is_int($value) ? $value : 0;
    }

    private function getSingleIntegerResult(Query $query): ?int
    {
        try {
            $value = $query->getSingleResult($query::HYDRATE_SINGLE_SCALAR);
            if (is_scalar($value)) {
                $value = (int) $value;
            }

            if (is_int($value)) {
                return $value;
            }
        } catch (NoResultException | NonUniqueResultException) {
        }

        return null;
    }
}
