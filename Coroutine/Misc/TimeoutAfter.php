<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\TaskTimeout;
use Async\Misc\Context;

/**
 * **Helper** class used by `timeout_after()` function when used as a context manager.
 * And only then usable when called by `with()`.
 *
 *For example:
 *```php
 * async_with(timeout_after($seconds);
 * // Or
 * yield with(timeout_after($seconds);
 * // Then
 *      yield statements();
 *      ...
 *```
 *
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/time.py#L36
 */
final class TimeoutAfter extends Context
{
  protected $clock = null;

  protected $expired = false;

  protected $result = true;

  public function __destruct()
  {
    $this->clock = null;
    $this->expired = null;
    $this->result = null;
  }

  public function __construct(float $seconds)
  {
    $this->clock = $seconds;
    $this->expired = false;
    $this->result = true;
  }

  public function timeout()
  {
    $coroutine = \coroutine();
    $timeout = $this->clock;
    $task = $this->withTask();
    $tid = $task->taskId();
    $contextTask = $task->getWith();
    $timeoutTask = function () use ($timeout, $task, $coroutine, $contextTask) {
      $task->setTimer();
      if (!$contextTask->exited()) {
        $timeError = new TaskTimeout($timeout);
        /** @var TaskGroup|ContextInterface */
        $contextTask = $task->hasGroup() ? $task->getGroup() : $contextTask;
        try {
          yield $contextTask->__exit($timeError);
        } catch (\Throwable $th) {
        }

        if ($task->hasGroup())
          $contextTask->task_done($task, true, $timeError);

        if ($task->hasCaller()) {
          $caller = $task->getCaller();
          $task->setCaller();
          $coroutine->schedule($caller);
        }

        $task->setException($timeError);
      }

      $coroutine->schedule($task);
    };

    $coroutine->createTask(\delayer(2, [$coroutine, 'addTimeout'], $timeoutTask, $timeout, $tid));
  }

  public function __invoke()
  {
    if (!$this->enter) {
      return $this->__enter();
    } elseif (!$this->exit) {
      try {
        return $this->timeout();
      } finally {
        parent::__destruct();
      }
    }
  }

  public function __enter()
  {
    $this->enter = true;

    if (!$this->isWith()) {
      yield $this->withSet();
    }
  }
}
