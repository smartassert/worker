<?php

namespace App\Repository;

use App\Entity\TestConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method null|TestConfiguration find($id, $lockMode = null, $lockVersion = null)
 * @method null|TestConfiguration findOneBy(array $criteria, array $orderBy = null)
 * @method TestConfiguration[]    findAll()
 * @method TestConfiguration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<TestConfiguration>
 */
class TestConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestConfiguration::class);
    }

    public function create(string $browser, string $url): TestConfiguration
    {
        $configuration = TestConfiguration::create($browser, $url);

        $this->_em->persist($configuration);
        $this->_em->flush();

        return $configuration;
    }

    public function findOneByConfiguration(TestConfiguration $configuration): ?TestConfiguration
    {
        return $this->findOneBy([
            'browser' => $configuration->getBrowser(),
            'url' => $configuration->getUrl(),
        ]);
    }
}
