<?php

declare(strict_types=1);

namespace Async\Misc;

/**
 * A Class implementing `Event` **objects**.
 * An **event** manages a flag that can be set
 * to _true_ with the `set()` method and reset to _false_ with the `clear()` method.
 * The `wait()` method blocks until the flag is _true_. The flag is initially _false_.
 *
 * @source https://github.com/python/cpython/blob/d2245cf190c36a6d74fe947bf133ce09d3313a6f/Lib/asyncio/locks.py#L157
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/sync.py#L30
 */
final class Event
{
  protected $value;

  public function __construct()
  {
    $this->value = false;
  }

  /**
   * Return _True_ if and only if the internal flag is `true`.
   *
   * @return boolean
   */
  public function is_set()
  {
    return $this->value;
  }

  /**
   * Set the internal flag to `true`.
   * All **coroutines** waiting for it to become _true_ are awakened.
   * Coroutine that call `wait()` once the flag is _true_ will not block at all.
   * - This function needs to be prefixed with `yield`
   *
   * @return void
   */
  public function set()
  {
    $this->value = true;

    yield;
  }

  /**
   * Reset the internal flag to `false`.
   * Subsequently, **coroutines** calling `wait()` will block until `set()` is called to set
   * the internal flag to `true` again.
   *
   * @return void
   */
  public function clear()
  {
    $this->value = false;
  }

  /**
   * Block until the internal flag is `true`.
   * If the internal flag is `true` on entry, return _True_ immediately.
   * Otherwise, block until another **coroutine** calls `set()` to set the flag to `true`, then return _True_.
   * - This function needs to be prefixed with `yield`
   *
   * @return bool
   */
  public function wait()
  {
    yield;

    if ($this->is_set())
      return true;

    while (!$this->is_set()) {
      yield;
    }

    return true;
  }
}
