<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\Misc\Context;

/**
 * A Semaphore implementation.
 * A `semaphore` manages an internal _counter_ which is decremented by each `acquire()` call
 * and incremented by each `release()` call.
 * The counter can never go below zero; when `acquire()` finds that it is zero, it blocks,
 * waiting until some other _thread_ calls `release()`.
 *
 * The optional argument gives the initial value for the internal
 * counter; it defaults to 1. If the value given is less than 0, **Error** is throw.
 *
 *
 *
 * @source https://github.com/python/cpython/blob/d2245cf190c36a6d74fe947bf133ce09d3313a6f/Lib/asyncio/locks.py#L332
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/sync.py#L184
 */
final class Semaphore extends Context
{
  public function __enter(): bool
  {
    $this->enter = true;
    return $this->acquire();
  }

  public function __exit(\Throwable $type = null)
  {
    if (!empty($type)) {
      $this->error = $type;
    }

    $this->exit = true;
    return $this->release();
  }

  /**
   * @var int
   */
  protected $value;

  public function __construct($value = 1)
  {
    if ($value < 0)
      \panic('Semaphore initial value must be >= 0');

    $this->value = $value;
  }

  /**
   * @return int
   */
  public function value(): int
  {
    return $this->value;
  }

  /**
   * Returns `true` if **semaphore** can not be acquired immediately.
   *
   * @return bool
   */
  public function locked()
  {
    return $this->value === 0;
  }

  /**
   * Acquire a `semaphore`.
   * If the internal _counter_ is larger than zero on entry, decrement it by one and return `true` immediately.
   * If it is zero on entry, **block**, waiting until some other `coroutine` has called `release()` to make
   * it larger than 0, and then return `true`.
   * - This function needs to be prefixed with `yield`
   *
   * @return true
   */
  public function acquire()
  {
    if ($this->value <= 0)
      return $this->_acquire();

    $this->value--;
    return true;
  }

  protected function _acquire()
  {
    try {
      while ($this->value <= 0)
        yield;
    } catch (\Throwable $e) {
      try {
        yield \kill_task();
      } catch (\Throwable $other) {
      }

      throw $e;
    }

    $this->value--;
    return true;
  }

  /**
   * Release a semaphore, incrementing the internal counter by one.
   * When it was zero on entry and another coroutine is waiting for it to
   * become larger than zero again, wake up that coroutine.
   * - This function needs to be prefixed with `yield`
   *
   * @return void
   */
  public function release()
  {
    if ($this->locked())
      return $this->_release();

    $this->value++;
  }

  protected function _release()
  {
    $this->value++;

    yield;
  }
}
