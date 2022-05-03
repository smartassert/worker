<?php

declare(strict_types=1);

namespace App\Services\EntityStore;

use App\Entity\Job;
use Doctrine\ORM\EntityManagerInterface;

class JobStore
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(): ?Job
    {
        $job = $this->entityManager->find(Job::class, Job::ID);

        return $job instanceof Job ? $job : null;
    }
}
