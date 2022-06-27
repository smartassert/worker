<?php

namespace App\Repository;

use App\Entity\WorkerEvent;
use App\Enum\WorkerEventOutcome;
use App\Enum\WorkerEventScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|WorkerEvent find($id, $lockMode = null, $lockVersion = null)
 * @method null|WorkerEvent findOneBy(array $criteria, array $orderBy = null)
 * @method WorkerEvent[]    findAll()
 * @method WorkerEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
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
        $this->_em->persist($workerEvent);
        $this->_em->flush();

        return $workerEvent;
    }

    public function hasForType(WorkerEventScope $scope, WorkerEventOutcome $outcome): bool
    {
        return $this->count([
            'scope' => $scope->value,
            'outcome' => $outcome->value,
        ]) > 0;
    }

    public function getTypeCount(WorkerEventScope $scope, WorkerEventOutcome $outcome): int
    {
        return $this->count([
            'scope' => $scope->value,
            'outcome' => $outcome->value,
        ]);
    }
}
