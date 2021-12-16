<?php

namespace Async;

use Async\Spawn\Channeled;
use Async\Spawn\FutureInterface;
use Async\Spawn\ParallelInterface as SpawnerInterface;

interface ParallelInterface extends SpawnerInterface
{
  /**
   * Create an `yield`able Future `sub/child` **task**, that can include an additional **file**.
   * This function exists to give same behavior as **parallel\runtime** of `ext-parallel` extension,
   * but without any of the it's limitations. All child output is displayed.
   * - This feature is for `Coroutine` package or any third party package using `yield` for execution.
   *
   * @param closure $future
   * @param string $include additional file to execute
   * @param Channeled|mixed|null ...$args - if a `Channel` instance is passed, it wil be used to set `Future` **IPC/CSP** handler
   *
   * @return FutureInterface
   * @see https://www.php.net/manual/en/parallel.run.php
   */
  public function adding(?\closure $future = null, ?string $include = null, ...$args): FutureInterface;

  /**
   * Try to cancel the Future.
   *
   * @param FutureInterface $future
   * @return void
   */
  public function cancel(FutureInterface $future): void;

  /**
   * Start and monitor the scheduled tasks.
   *
   * @param FutureInterface $future
   * @return void
   */
  public function tick(FutureInterface $future);
}
