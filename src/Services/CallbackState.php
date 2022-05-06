<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Callback\CallbackEntity;
use App\Repository\CallbackRepository;

class CallbackState implements \Stringable
{
    public const STATE_AWAITING = 'awaiting';
    public const STATE_RUNNING = 'running';
    public const STATE_COMPLETE = 'complete';

    public function __construct(
        private CallbackRepository $repository
    ) {
    }

    /**
     * @return self::STATE_*
     */
    public function __toString(): string
    {
        $callbackCount = $this->repository->count([]);
        $finishedCallbackCount = $this->repository->count([
            'state' => [
                CallbackEntity::STATE_FAILED,
                CallbackEntity::STATE_COMPLETE,
            ],
        ]);

        if (0 === $callbackCount) {
            return self::STATE_AWAITING;
        }

        return $finishedCallbackCount === $callbackCount
            ? self::STATE_COMPLETE
            : self::STATE_RUNNING;
    }

    /**
     * @param CallbackState::STATE_* ...$states
     */
    public function is(...$states): bool
    {
        $states = array_filter($states, function ($item) {
            return is_string($item);
        });

        return in_array((string) $this, $states);
    }
}
