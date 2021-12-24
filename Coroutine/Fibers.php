<?php

declare(strict_types=1);

namespace Async\Fibers;

use Fiber as Fiber_81;
use Async\Co;
use Async\Fiber;

if (!\function_exists('fiberizing')) {
  /**
   * @param string $tag an instance name
   * @param callable $function Function to invoke when starting the fiber.
   * @return void
   */
  function creating_fiber(string $tag, callable $function): void
  {
    Co::addFiber($tag, (new Fiber($function)));
  }

  /**
   * Suspend execution of the fiber. The fiber may be resumed with {@see Fiber::resume()} or {@see Fiber::throw()}.
   * Cannot be called from {main}.
   * - This function needs to be prefixed with `yield`.
   *
   * @param mixed $with Value to return from {@see Fiber::resume()} or {@see Fiber::throw()}.
   *
   * @return mixed Value provided to {@see Fiber::resume()}.
   *
   * @throws Throwable Exception provided to {@see Fiber::throw()}.
   */
  function suspending($with = null)
  {
    return Fiber::suspend($with);
  }

  /**
   * Starts execution of the fiber. Returns when the fiber suspends or terminates.
   * - This function needs to be prefixed with `yield`.
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
   * - This function needs to be prefixed with `yield`.
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
   * - This function needs to be prefixed with `yield`.
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
  function fiberizing(string $tag)
  {
    if (Co::isFiber($tag)) {
      $fiber = Co::getFiber($tag);
      Co::clearFiber($tag);
      return $fiber->getReturn();
    }
  }

  /**
   * - This function is for **PHP 8.1**.
   * @param string $tag an instance name
   * @param callable $function Function to invoke when starting the fiber.
   * @return void
   */
  function create_fiber(string $tag, callable $function): void
  {
    if (\class_exists('\Fiber', false))
      Co::addFiber($tag, (new Fiber_81($function)));
  }

  /**
   * Suspend execution of the fiber. The fiber may be resumed with {@see Fiber::resume()} or {@see Fiber::throw()}.
   * Cannot be called from {main}.
   * - This function is for **PHP 8.1**.
   *
   * @param mixed $with Value to return from {@see Fiber::resume()} or {@see Fiber::throw()}.
   *
   * @return mixed Value provided to {@see Fiber::resume()}.
   *
   * @throws Throwable Exception provided to {@see Fiber::throw()}.
   */
  function suspend_fiber($with = null)
  {
    if (\class_exists('\Fiber', false))
      return Fiber_81::suspend($with);
  }

  /**
   * Starts execution of the fiber. Returns when the fiber suspends or terminates.
   * - This function is for **PHP 8.1**.
   *
   * @param string $tag an instance name
   * @param mixed ...$with Arguments passed to fiber function.
   *
   * @return mixed Value from the first suspension point.
   *
   * @throw FiberError If the fiber is running or terminated.
   * @throw Throwable If the fiber callable throws an uncaught exception.
   */
  function start_fiber(string $tag, ...$with)
  {
    return starting($tag, ...$with);
  }

  /**
   * Resumes the fiber, returning the given value from {@see Fiber::suspend()}.
   * Returns when the fiber suspends or terminates.
   * - This function is for **PHP 8.1**.
   *
   * @param string $tag an instance name
   * @param mixed $with
   *
   * @return mixed Value from the next suspension point or NULL if the fiber terminates.
   *
   * @throw FiberError If the fiber is running or terminated.
   * @throw Throwable If the fiber callable throws an uncaught exception.
   */
  function resume_fiber(string $tag = null, $with = null)
  {
    return resuming($tag, $with);
  }

  /**
   * Throws the given exception into the fiber from {@see Fiber::suspend()}.
   * Returns when the fiber suspends or terminates.
   * - This function is for **PHP 8.1**.
   *
   * @param string $tag an instance name
   * @param \Throwable $error
   *
   * @return mixed Value from the next suspension point or NULL if the fiber terminates.
   *
   * @throw FiberError If the fiber is running or terminated.
   * @throw Throwable If the fiber callable throws an uncaught exception.
   */
  function throw_fiber(string $tag, \Throwable $error)
  {
    return throwing($tag, $error);
  }

  /**
   * Get any return value and clear out the `tag` instance from `Co` static class.
   * @param string $tag an instance name
   * @return mixed Return value of the fiber callback.
   *
   * @throws FiberError If the fiber has not terminated or did not return a value.
   */
  function fiberize(string $tag)
  {
    return fiberizing($tag);
  }
}
