<?php

declare(strict_types=1);

namespace Async;

use Async\Exceptions\QueueFull;
use Async\Exceptions\QueueEmpty;
use Async\Exceptions\LengthException;

/**
 * A `queue`, useful for coordinating _producer_ and _consumer_ coroutines.
 * This class is a asynchronous wrapper around standard PHP **SplQueue** library.
 *
 * @see https://curio.readthedocs.io/en/latest/reference.html#queues
 * @see https://docs.python.org/3.10/library/asyncio-queue.html#asyncio-queues
 * @source https://github.com/python/cpython/blob/3.10/Lib/asyncio/queues.py
 *
 * @codeCoverageIgnore
 */
final class Queue
{
  protected $max_size = 0;
  protected $queue = null;
  protected $finished = false;
  protected $unfinished_tasks = 0;

  public function __destruct()
  {
    unset($this->queue);
    $this->queue = null;
    $this->max_size = null;
    $this->finished = null;
    $this->unfinished_tasks = null;
  }

  protected function _get()
  {
    return $this->queue->dequeue();
  }

  protected function _put($item)
  {
    $this->queue->enqueue($item);
  }

  /**
   * If maxsize is less than or equal to zero, the queue size is infinite. If it
   * is an integer greater than `0`, then `put()` will block when the
   * queue reaches maxsize, until an item is removed by `get()`.
   *
   * @param integer $maxsize
   */
  public function __construct(int $maxsize = 0)
  {
    $this->max_size = $maxsize;
    $this->finished = true;
    $this->queue =  new \SplQueue();
  }

  /**
   * Number of items in the queue.
   *
   * @return integer
   */
  public function size(): int
  {
    return $this->queue->count();
  }

  /**
   * Number of items allowed in the queue.
   *
   * @return integer
   */
  public function maxsize(): int
  {
    return $this->max_size;
  }

  /**
   * Return `True` if the queue is empty, `False` otherwise.
   *
   * @return bool
   */
  public function empty(): bool
  {
    return $this->queue->isEmpty();
  }

  /**
   * Return `True` if there are maxsize items in the queue.
   *
   * Note: if the Queue was initialized with maxsize=0 (the default),
   * then `full()` is never `True`.
   *
   * @return bool
   */
  public function full(): bool
  {
    if ($this->max_size <= 0)
      return false;

    return $this->size() >= $this->max_size;
  }

  /**
   * Put an `item` into the queue.
   *
   * Put an item into the queue. If the queue is full, `wait` until a free
   * slot is available before adding item.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @param mixed $item
   * @return void
   */
  public function put($item)
  {
    while ($this->full()) {
      try {
        yield;
      } catch (\Throwable $e) {
        yield \kill_task();
        throw $e;
      }
    }

    return $this->put_nowait($item);
  }

  /**
   * Put an `item` into the queue without blocking.
   *
   * @param mixed $item
   * @return void
   * @throws QueueFull if no free slot is immediately available
   */
  public function put_nowait($item): void
  {
    if ($this->full())
      throw new QueueFull("No free slot available!");

    $this->_put($item);
    $this->unfinished_tasks++;
    $this->finished = false;
  }

  /**
   * Remove and return an `item` from the queue.
   *
   * If queue is empty, `wait` until an item is available.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @return mixed
   */
  public function get()
  {
    while ($this->empty()) {
      try {
        yield;
      } catch (\Throwable $e) {
        yield \kill_task();
        throw $e;
      }
    }

    return $this->get_nowait();
  }

  /**
   * Remove and return an item from the queue.
   *
   * @return mixed
   * @throws QueueEmpty if no item is immediately available
   */
  public function get_nowait()
  {
    if ($this->empty())
      throw new QueueEmpty('No item available!');

    $item = $this->_get();
    return $item;
  }

  /**
   * Indicate that a formerly `enqueued` task is complete.
   *
   * Used by queue _consumers_. For each `get()` used to fetch a task,
   * a subsequent call to `task_done()` tells the queue that the processing
   * on the task is complete.
   *
   * If a `join()` is currently blocking, it will resume when all items have
   * been processed (meaning that a `task_done()` call was received for every
   * item that had been `put()` into the queue).
   *
   * @throws LengthException if called more times than there were items placed in
   * the queue.
   * @return void
   */
  public function task_done(): void
  {
    if ($this->unfinished_tasks <= 0)
      throw new LengthException('task_done() called too many times!');

    $this->unfinished_tasks--;
    if ($this->unfinished_tasks == 0)
      $this->finished = true;
  }

  /**
   * Block until all items in the queue have been gotten and processed.
   *
   * The count of unfinished tasks goes up whenever an item is added to the
   * queue. The count goes down whenever a consumer calls `task_done()` to
   * indicate that the item was retrieved and all work on it is complete.
   * When the count of unfinished tasks drops to zero, `join()` unblocks.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @return void
   */
  public function join()
  {
    $yielding = $this->unfinished_tasks;
    if ($this->unfinished_tasks > 0) {
      while (!$this->finished) {
        foreach (\range(0, $yielding) as $nan)
          yield;
      }
    }
  }
}
