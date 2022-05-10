<?php

declare(strict_types=1);

namespace App\Entity\Callback;

use App\Repository\CallbackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CallbackRepository::class)]
class CallbackEntity implements CallbackInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * @var self::STATE_*
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $state;

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
        $callback = new CallbackEntity();
        $callback->state = self::STATE_AWAITING;
        $callback->type = $type;
        $callback->reference = $reference;
        $callback->payload = $payload;

        return $callback;
    }

    public function getEntity(): CallbackEntity
    {
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return self::STATE_*
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param self::STATE_* $state
     */
    public function hasState(string $state): bool
    {
        return $state === $this->state;
    }

    /**
     * @param self::STATE_* $state
     */
    public function setState(string $state): void
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
