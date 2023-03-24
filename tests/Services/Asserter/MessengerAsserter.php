<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerAsserter
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function getEnvelopeAtPosition(int $index): Envelope
    {
        $envelope = null;

        $queue = $this->transport->get();
        foreach ($queue as $queueIndex => $currentEnvelope) {
            if ($queueIndex === $index) {
                $envelope = $currentEnvelope;
            }
        }

        TestCase::assertInstanceOf(Envelope::class, $envelope);
        if (!$envelope instanceof Envelope) {
            throw new \InvalidArgumentException('No envelope at position ' . $index);
        }

        return $envelope;
    }

    public function assertMessageAtPositionEquals(int $index, object $expectedMessage): void
    {
        TestCase::assertEquals(
            $expectedMessage,
            $this->getEnvelopeAtPosition($index)->getMessage()
        );
    }
}
