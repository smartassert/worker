<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Callback\CallbackEntity;
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
        if ($callback instanceof CallbackEntity) {
            $this->stateMutator->setFailed($callback);
        }
    }
}
