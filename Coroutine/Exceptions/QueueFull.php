<?php

namespace Async;

use Async\RuntimeException;

/**
 * Throws when the `Queue::put_nowait()` method is called on a **full** `Queue`.
 */
class QueueFull extends RuntimeException
{
}
