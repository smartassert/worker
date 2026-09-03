<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedComponentState array{
 *    state: non-empty-string,
 *    meta_state: array{
 *      pending: bool,
 *      ended: bool,
 *      succeeded: bool,
 *    },
 *  }
 */
interface SerializableComponentStateInterface extends \JsonSerializable
{
    /**
     * @return SerializedComponentState
     */
    public function jsonSerialize(): array;

    /**
     * @return SerializedComponentState
     */
    public function toArray(): array;
}
