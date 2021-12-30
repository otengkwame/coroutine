<?php

declare(strict_types=1);

namespace Async\Queues;

use Async\Co;
use Async\Queue;

if (!\function_exists('queue_clear')) {

  /**
   * If maxsize is less than or equal to zero, the queue size is infinite. If it
   * is an integer greater than `0`, then `queue_put()` will block when the
   * queue reaches maxsize, until an item is removed by `queue_get()`.
   *
   * @param string $tag an instance name
   * @param integer $maxsize
   * @return void
   * @throws Panic if **tag** `name` already exists.
   */
  function create_queue(string $tag, int $maxsize = 0): void
  {
    Co::addQueue($tag, (new Queue($maxsize)));
  }

  /**
   * Remove and return an `item` from the queue.
   *
   * If queue is `empty`, **wait** until an `item` is available.
   * - This function needs to be prefixed with `yield`.
   *
   * @param string $tag an instance name
   *
   * @return mixed
   */
  function queue_get(string $tag)
  {
    if (Co::isQueue($tag))
      return Co::getQueue($tag)->get();
  }

  /**
   * Put an `item` into the queue.
   *
   * If the queue is `full`, **wait** until a free slot is available before adding `item`.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $tag an instance name
   * @param mixed $item
   *
   * @return void
   */
  function queue_put(string $tag, $item)
  {
    if (Co::isQueue($tag))
      return Co::getQueue($tag)->put($item);
  }

  /**
   * Indicate that a formerly `enqueued` task is complete.
   *
   * Used by queue _consumers_. For each `queue_get()` used to fetch a task,
   * a subsequent call to `queue_done()` tells the queue that the processing
   * on the task is complete.
   *
   * If a `queue_join()` is currently blocking, it will resume when all items have
   * been processed (meaning that a `queue_done()` call was received for every
   * item that had been `queue_put()` into the queue).
   *
   * @param string $tag an instance name
   * @return void
   * @throws LengthException if called more times than there were items placed in
   * the queue.
   */
  function queue_done(string $tag)
  {
    if (Co::isQueue($tag))
      return Co::getQueue($tag)->task_done();
  }

  /**
   * Block until all items in the queue have been gotten and processed.
   *
   * The count of unfinished tasks goes up whenever an item is added to the
   * queue. The count goes down whenever a consumer calls `queue_done()` to
   * indicate that the item was retrieved and all work on it is complete.
   * When the count of unfinished tasks drops to zero, `queue_join()` unblocks.
   * - This function needs to be prefixed with `yield`
   *
   * @param string $tag an instance name
   * @return void
   */
  function queue_join(string $tag)
  {
    if (Co::isQueue($tag))
      return Co::getQueue($tag)->join();
  }

  /**
   * Clear out the `tag` **Queue** instance from `Co` static class.
   *
   * @param string $tag an instance name
   * @return void
   */
  function queue_clear(string $tag): void
  {
    if (Co::isQueue($tag))
      Co::clearQueue($tag);
  }
}
