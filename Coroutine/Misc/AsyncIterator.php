<?php

declare(strict_types=1);

namespace Async\Misc;

/**
 * An `asynchronous` iterable is able to call asynchronous code in its iter implementation, and asynchronous iterator can
 * call asynchronous code in its `current` method.
 *
 * An object that implements a **Iterator** with _only_ `valid()`, and `current()` methods.
 *
 * The __`current()`__ method MUST return an **awaitable** object.
 * `async_for()` resolves the _awaitables_ returned by an asynchronous `iterator’s` __`current()`__ method.
 *
 * @see https://www.python.org/dev/peps/pep-0492/#asynchronous-iterators-and-async-for
 */
abstract class AsyncIterator implements \Iterator
{
  /**
   * Return the current element. The contents MUST include `yield` to be considered a **Async** `Iterator`.
   * - THIS METHOD MUST BE IMPLEMENTED AND OVERWRITTEN.
   * - This method WILL BE called using `yield` to attain a _single_ **result** for processing.
   *
   * @return mixed Can return any type.
   */
  public function current()
  {
    $result =  yield $this;
    unset($result[1]);
    $this->next();

    return $result;
  }

  /**
   * Checks if current position is valid.
   * - THIS METHOD MUST BE IMPLEMENTED AND OVERWRITTEN.
   *
   * @return bool Returns true on success or false on failure.
   */
  public function valid(): bool
  {
    return !\is_null(\array_key_first([1]));
  }

  /**
   * DO NOT IMPLEMENT, NOT USED.
   *
   * @return string|int|null TKey on success, or null on failure.
   */
  public function key()
  {
    static $index;

    return \array_key_first([$index]);
  }

  /**
   * DO NOT IMPLEMENT, NOT USED.
   *
   * @return void
   */
  public function rewind(): void
  {
    static $index;

    $index--;
  }

  /**
   * DO NOT IMPLEMENT, NOT USED.
   *
   * @return void
   */
  public function next(): void
  {
    static $index;

    $index++;
  }
}
