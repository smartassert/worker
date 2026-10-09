<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Enum\WorkerEventState;
use App\Repository\WorkerEventRepository;

class CreateCompileExecuteEventDeliveryFailureTest extends AbstractCreateCompileExecuteTest
{
    public function testMain(): void
    {
        $this->doWaitForApplicationToFinish();

        $workerEventRepository = self::getContainer()->get(WorkerEventRepository::class);
        \assert($workerEventRepository instanceof WorkerEventRepository);

        $workerEvents = $workerEventRepository->findAll();
        self::assertNotEmpty($workerEvents);
        foreach ($workerEvents as $workerEvent) {
            self::assertSame(WorkerEventState::FAILED, $workerEvent->getState());
        }
    }

    protected static function getEventNotifyUrl(): string
    {
        return 'https://localhost:8081/status/418';
    }
}
