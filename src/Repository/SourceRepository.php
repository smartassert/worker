<?php

namespace App\Repository;

use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 */
class SourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    public function add(Source $source): Source
    {
        $this->_em->persist($source);
        $this->_em->flush();

        return $source;
    }

    /**
     * @param null|Source::TYPE_* $type
     *
     * @return non-empty-string[]
     */
    public function findAllPaths(?string $type = null): array
    {
        $queryBuilder = $this
            ->createQueryBuilder('Source')
            ->select('Source.path')
        ;

        if (is_string($type)) {
            $queryBuilder
                ->where('Source.type = :type')
                ->setParameter('type', $type)
            ;
        }

        $query = $queryBuilder->getQuery();
        $result = $query->getArrayResult();

        $paths = [];
        foreach ($result as $item) {
            if (is_array($item)) {
                $path = $item['path'] ?? '';
                if (is_string($path) && '' !== $path) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }
}
