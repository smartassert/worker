<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\EntityStore;

use App\Entity\Job;
use App\Services\EntityPersister;
use App\Services\EntityStore\JobStore;
use App\Tests\AbstractBaseFunctionalTest;
use App\Tests\Services\EntityRemover;

class JobStoreTest extends AbstractBaseFunctionalTest
{
    private JobStore $store;
    private EntityPersister $persister;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(JobStore::class);
        self::assertInstanceOf(JobStore::class, $store);
        if ($store instanceof JobStore) {
            $this->store = $store;
        }

        $persister = self::getContainer()->get(EntityPersister::class);
        self::assertInstanceOf(EntityPersister::class, $persister);
        if ($persister instanceof EntityPersister) {
            $this->persister = $persister;
        }

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeForEntity(Job::class);
        }
    }

    public function testHas(): void
    {
        self::assertNull($this->store->get());

        $this->persister->persist(Job::create('label content', 'http://example.com/callback', 600));
        self::assertNotNull($this->store->get());
    }

    public function testGet(): void
    {
        $job = Job::create('label content', 'http://example.com/callback', 600);
        $this->persister->persist($job);

        self::assertSame($this->store->get(), $job);
    }
}
