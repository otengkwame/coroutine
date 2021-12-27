<?php

namespace Async\Exceptions;

use Async\Exceptions\RuntimeException;

/**
 * Throws when `Queue::get_nowait()` is called on an **empty** `Queue`.
 */
class QueueEmpty extends RuntimeException
{
}
