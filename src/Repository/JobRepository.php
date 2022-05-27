<?php

namespace App\Repository;

use App\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Job>
 *
 * @method null|Job find($id, $lockMode = null, $lockVersion = null)
 * @method null|Job findOneBy(array $criteria, array $orderBy = null)
 * @method Job[]    findAll()
 * @method Job[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    public function add(Job $job): Job
    {
        $this->_em->persist($job);
        $this->_em->flush();

        return $job;
    }

    /**
     * @param non-empty-string $label
     */
    public function create(string $label, string $eventDeliveryUrl, int $maximumDurationInSeconds): Job
    {
        return $this->add(new Job($label, $eventDeliveryUrl, $maximumDurationInSeconds));
    }

    public function get(): ?Job
    {
        return parent::findOneBy([]);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function remove(Job $entity, bool $flush = true): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }
}
