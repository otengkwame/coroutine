<?php

declare(strict_types=1);

namespace parallel;

/**
 * When an `Event` is returned,
 * `Event::$object` shall be removed from the loop that returned it,
 * should the event be a write event the Input for `Event::$source`
 * shall also be removed.
 *
 * @codeCoverageIgnore
 */
abstract class Event
{
    /**
     * Shall be one of Event\Type constants
     * @var int
     */
    public $type;

    /**
     * Shall be the source of the event (target name)
     * @var string
     */
    public $source;

    /**
     * Shall be either Future or Channel
     * @var object
     */
    public $object;

    /**
     * Shall be set for Read/Error events
     * @var int
     */
    public $value;
}
