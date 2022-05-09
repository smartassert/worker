<?php

namespace App\Repository;

use App\Entity\WorkerEvent;
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
class CallbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerEvent::class);
    }

    /**
     * @param WorkerEvent::TYPE_* $type
     * @param non-empty-string    $reference
     * @param array<mixed>        $payload
     */
    public function create(string $type, string $reference, array $payload): WorkerEvent
    {
        $callback = WorkerEvent::create($type, $reference, $payload);

        $this->_em->persist($callback);
        $this->_em->flush();

        return $callback;
    }

    public function hasForType(string $type): bool
    {
        return $this->count(['type' => $type]) > 0;
    }

    /**
     * @param WorkerEvent::TYPE_* $type
     */
    public function getTypeCount(string $type): int
    {
        return $this->count([
            'type' => $type,
        ]);
    }
}
