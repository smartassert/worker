<?php

namespace App\Repository;

use App\Entity\Job;
use App\Exception\JobNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Job>
 */
class JobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    public function add(Job $job): Job
    {
        $this->getEntityManager()->persist($job);
        $this->getEntityManager()->flush();

        return $job;
    }

    public function has(): bool
    {
        return parent::findOneBy([]) instanceof Job;
    }

    /**
     * @throws JobNotFoundException
     */
    public function get(): Job
    {
        $job = parent::findOneBy([]);
        if ($job instanceof Job) {
            return $job;
        }

        throw new JobNotFoundException();
    }

    public function remove(Job $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
