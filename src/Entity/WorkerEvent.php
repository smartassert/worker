<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkerEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkerEventRepository::class)]
class WorkerEvent
{
    public const TYPE_JOB_STARTED = 'job/started';
    public const TYPE_JOB_TIME_OUT = 'job/timed-out';
    public const TYPE_JOB_COMPLETED = 'job/completed';
    public const TYPE_JOB_FAILED = 'job/failed';
    public const TYPE_JOB_COMPILED = 'job/compiled';
    public const TYPE_COMPILATION_STARTED = 'compilation/started';
    public const TYPE_COMPILATION_PASSED = 'compilation/passed';
    public const TYPE_COMPILATION_FAILED = 'compilation/failed';
    public const TYPE_EXECUTION_STARTED = 'execution/started';
    public const TYPE_EXECUTION_COMPLETED = 'execution/completed';
    public const TYPE_TEST_STARTED = 'test/started';
    public const TYPE_TEST_PASSED = 'test/passed';
    public const TYPE_TEST_FAILED = 'test/failed';
    public const TYPE_STEP_PASSED = 'step/passed';
    public const TYPE_STEP_FAILED = 'step/failed';

    public const TYPE_UNKNOWN = 'unknown';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, enumType: WorkerEventState::class)]
    private WorkerEventState $state;

    /**
     * @var self::TYPE_*
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $type;

    #[ORM\Column(type: 'string', length: 32)]
    private string $reference;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $payload;

    /**
     * @param self::TYPE_*     $type
     * @param non-empty-string $reference
     * @param array<mixed>     $payload
     */
    public static function create(string $type, string $reference, array $payload): self
    {
        $entity = new WorkerEvent();
        $entity->state = WorkerEventState::AWAITING;
        $entity->type = $type;
        $entity->reference = $reference;
        $entity->payload = $payload;

        return $entity;
    }

    public function getEntity(): WorkerEvent
    {
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getState(): WorkerEventState
    {
        return $this->state;
    }

    public function hasState(WorkerEventState $state): bool
    {
        return $state === $this->state;
    }

    public function setState(WorkerEventState $state): void
    {
        $this->state = $state;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
