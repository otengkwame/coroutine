<?php

declare(strict_types=1);

namespace Async\Fibers;

use Fiber as Fiber_81;
use Async\Co;
use Async\Fiber;

if (!\function_exists('fiber_return')) {
  /**
   * @param string $tag an instance name
   * @param callable $function Function to invoke when starting the fiber.
   * @return void
   */
  function create_fiber(string $tag, callable $function): void
  {
    Co::addFiber($tag, (\class_exists('\Fiber', false) ? new Fiber_81($function) : new Fiber($function)));
  }

  /**
   * Suspend execution of the fiber. The fiber may be resumed with {@see Fiber::resume()} or {@see Fiber::throw()}.
   * Cannot be called from {main}.
   * - This function needs to be prefixed with `yield`, If not using **PHP 8.1**.
   *
   * @param mixed $with Value to return from {@see Fiber::resume()} or {@see Fiber::throw()}.
   *
   * @return mixed Value provided to {@see Fiber::resume()}.
   *
   * @throws Throwable Exception provided to {@see Fiber::throw()}.
   */
  function suspending($with = null)
  {
    if (\class_exists('\Fiber', false))
      return Fiber_81::suspend($with);

    return Fiber::suspend($with);
  }

  /**
   * Starts execution of the fiber. Returns when the fiber suspends or terminates.
   * - This function needs to be prefixed with `yield`, If not using **PHP 8.1**.
   *
   * @param string $tag an instance name
   * @param mixed ...$with Arguments passed to fiber function.
   *
   * @return mixed Value from the first suspension point.
   *
   * @throw FiberError If the fiber is running or terminated.
   * @throw Throwable If the fiber callable throws an uncaught exception.
   */
  function starting(string $tag, ...$with)
  {
    if (Co::isFiber($tag))
      return Co::getFiber($tag)->start(...$with);
  }

  /**
   * Resumes the fiber, returning the given value from {@see Fiber::suspend()}.
   * Returns when the fiber suspends or terminates.
   * - This function needs to be prefixed with `yield`, If not using **PHP 8.1**.
   *
   * @param string $tag an instance name
   * @param mixed $with
   *
   * @return mixed Value from the next suspension point or NULL if the fiber terminates.
   *
   * @throw FiberError If the fiber is running or terminated.
   * @throw Throwable If the fiber callable throws an uncaught exception.
   */
  function resuming(string $tag = null, $with = null)
  {
    if (Co::isFiber($tag))
      return Co::getFiber($tag)->resume($with);
  }

  /**
   * Throws the given exception into the fiber from {@see Fiber::suspend()}.
   * Returns when the fiber suspends or terminates.
   * - This function needs to be prefixed with `yield`, If not using **PHP 8.1**.
   *
   * @param string $tag an instance name
   * @param \Throwable $error
   *
   * @return mixed Value from the next suspension point or NULL if the fiber terminates.
   *
   * @throw FiberError If the fiber is running or terminated.
   * @throw Throwable If the fiber callable throws an uncaught exception.
   */
  function throwing(string $tag, \Throwable $error)
  {
    if (Co::isFiber($tag)) {
      $fiber = Co::getFiber($tag);
      Co::clearFiber($tag);
      return $fiber->throw($error);
    }
  }

  /**
   * Get any return value and clear out the `tag` instance from `Co` static class.
   * @param string $tag an instance name
   * @return mixed Return value of the fiber callback.
   *
   * @throws FiberError If the fiber has not terminated or did not return a value.
   */
  function fiber_return(string $tag)
  {
    if (Co::isFiber($tag)) {
      $fiber = Co::getFiber($tag);
      Co::clearFiber($tag);
      return $fiber->getReturn();
    }
  }
}
