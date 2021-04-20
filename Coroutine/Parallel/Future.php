<?php

declare(strict_types=1);

namespace parallel;

use Async\ParallelInterface;
use parallel\FutureInterface as Futures;
use Async\Spawn\FutureInterface;

final class Future implements Futures
{
  /**
   * @var ParallelInterface
   */
  private $parallel = [];

  /**
   * @var FutureInterface
   */
  private $future = null;

  public function __destruct()
  {
    $this->parallel->wait();
    $this->future = null;
    $this->parallel = null;
  }

  /* Create */
  public function __construct(RuntimeInterface $runtime)
  {
    $this->future = $runtime->getFuture();
    $this->parallel = $runtime->getParallel();
  }

  /* Resolution */
  public function value()
  {
    $this->parallel->wait();
    return $this->future->getResult();
  }

  /* State */
  public function cancelled(): bool
  {
    return $this->future->isTerminated() && !$this->future->isSuccessful();
  }

  public function done(): bool
  {
    return !$this->future->isRunning();
  }

  /* Cancellation */
  public function cancel(): bool
  {
    return $this->future->stop()->isTerminated();
  }
}
