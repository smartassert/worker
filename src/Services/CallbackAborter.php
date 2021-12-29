<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Callback\CallbackInterface;
use App\Repository\CallbackRepository;

class CallbackAborter
{
    public function __construct(
        private CallbackRepository $repository,
        private CallbackStateMutator $stateMutator,
    ) {
    }

    public function abort(int $id): void
    {
        $callback = $this->repository->find($id);
        if ($callback instanceof CallbackInterface) {
            $this->stateMutator->setFailed($callback);
        }
    }
}
