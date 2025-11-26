<?php

namespace App\Repository;

use App\Entity\WorkerEventReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkerEventReference>
 */
class WorkerEventReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerEventReference::class);
    }

    public function add(WorkerEventReference $entity): WorkerEventReference
    {
        $this->_em->persist($entity);
        $this->_em->flush();

        return $entity;
    }
}
