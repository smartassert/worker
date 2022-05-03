<?php

namespace App\Repository;

use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|Source find($id, $lockMode = null, $lockVersion = null)
 * @method null|Source findOneBy(array $criteria, array $orderBy = null)
 * @method Source[]    findAll()
 * @method Source[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<Source>
 */
class SourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    /**
     * @param Source::TYPE_* $type
     */
    public function create(string $type, string $path): Source
    {
        $source = Source::create($type, $path);

        $this->_em->persist($source);
        $this->_em->flush();

        return $source;
    }
}
