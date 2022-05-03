<?php

namespace App\Repository;

use App\Entity\Callback\CallbackEntity;
use App\Entity\Callback\CallbackInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|CallbackEntity find($id, $lockMode = null, $lockVersion = null)
 * @method null|CallbackEntity findOneBy(array $criteria, array $orderBy = null)
 * @method CallbackEntity[]    findAll()
 * @method CallbackEntity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<CallbackEntity>
 */
class CallbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CallbackEntity::class);
    }

    /**
     * @param CallbackInterface::TYPE_* $type
     * @param array<mixed>              $payload
     */
    public function create(string $type, array $payload): CallbackInterface
    {
        $callback = CallbackEntity::create($type, $payload);

        $this->_em->persist($callback);
        $this->_em->flush();

        return $callback;
    }

    public function hasForType(string $type): bool
    {
        return $this->count(['type' => $type]) > 0;
    }
}
