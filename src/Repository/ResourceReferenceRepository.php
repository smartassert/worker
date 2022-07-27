<?php

namespace App\Repository;

use App\Entity\ResourceReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResourceReference>
 *
 * @method null|ResourceReference find($id, $lockMode = null, $lockVersion = null)
 * @method null|ResourceReference findOneBy(array $criteria, array $orderBy = null)
 * @method ResourceReference[]    findAll()
 * @method ResourceReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ResourceReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResourceReference::class);
    }

    public function add(ResourceReference $entity): ResourceReference
    {
        $this->_em->persist($entity);
        $this->_em->flush();

        return $entity;
    }
}
