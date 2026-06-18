<?php

namespace App\Repository;

use App\Entity\WorkerEvent;
use App\Event\EmittableEvent\EventTypeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkerEvent>
 */
class WorkerEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerEvent::class);
    }

    public function add(WorkerEvent $workerEvent): WorkerEvent
    {
        $this->getEntityManager()->persist($workerEvent);
        $this->getEntityManager()->flush();

        return $workerEvent;
    }

    /**
     * @param EventTypeInterface::* $type
     */
    public function hasForType(string $type): bool
    {
        return $this->count(['type' => $type]) > 0;
    }

    /**
     * @param EventTypeInterface::* $type
     */
    public function getTypeCount(string $type): int
    {
        return $this->count(['type' => $type]);
    }

    /**
     * @return int[]
     */
    public function findAllIds(): array
    {
        $queryBuilder = $this->createQueryBuilder('WorkerEvent');

        $queryBuilder
            ->select('WorkerEvent.id')
            ->orderBy('WorkerEvent.id', 'ASC')
        ;

        $query = $queryBuilder->getQuery();

        $result = $query->getSingleColumnResult();
        $filteredResult = [];

        foreach ($result as $item) {
            if (is_int($item)) {
                $filteredResult[] = $item;
            }
        }

        return $filteredResult;
    }
}
