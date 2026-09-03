<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-import-type SerializedComponentState from SerializableComponentStateInterface
 *
 * @phpstan-type SerializedApplicationState array{
 *     application: SerializedComponentState,
 *     compilation: SerializedComponentState,
 *     execution: SerializedComponentState,
 *     event_delivery: SerializedComponentState
 * }
 */
interface SerializableApplicationStateInterface extends \JsonSerializable
{
    /**
     * @return SerializedApplicationState
     */
    public function jsonSerialize(): array;

    /**
     * @return SerializedApplicationState
     */
    public function toArray(): array;
}
