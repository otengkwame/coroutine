<?php

namespace Async\Exceptions;

use Async\Exceptions\RuntimeException;

/**
 * Throws when the `Queue::put_nowait()` method is called on a **full** `Queue`.
 */
class QueueFull extends RuntimeException
{
}
