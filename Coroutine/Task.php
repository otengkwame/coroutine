<?php

declare(strict_types=1);

namespace Async;

use Async\Coroutine;
use Async\TaskInterface;
use Async\FiberInterface;
use Async\CancelledError;
use Async\InvalidStateError;
use Async\Misc\TaskGroup;
use Async\Misc\ContextInterface;
use Async\Spawn\FutureInterface;

/**
 * Task is used to schedule coroutines concurrently.
 * When a coroutine is wrapped into a Task with functions like __create_task__, __away__, __spawner__, or __await(spawn)__
 * the coroutine is automatically scheduled to run soon.
 *
 * This Task class can also be seen to operate like an Fiber according to the RFC spec https://wiki.php.net/rfc/fiber
 *
 * @see https://curio.readthedocs.io/en/latest/reference.html#tasks
 * @source https://github.com/dabeaz/curio/blob/master/curio/task.py#L93
 * @source https://github.com/python/cpython/blob/576e38f9db61ca09ca6dc57ad13b02a7bf9d370a/Lib/asyncio/tasks.py
 */
final class Task implements TaskInterface
{
  const ERROR_MESSAGES = [
    'The operation has been cancelled, with: ',
    'The operation has exceeded the given deadline: ',
    'The task has erred: ',
    'Invalid internal state called on: '
  ];

  /**
   * The task’s id.
   *
   * @var int
   */
  protected $taskId;

  /**
   * A flag that indicates whether or not a task is daemonic.
   *
   * @var bool
   */
  protected $daemon;

  /**
   * The number of scheduling cycles the task has completed.
   * This might be useful if you’re trying to figure out if a task is running or not.
   * Or if you’re trying to monitor a task’s progress.
   *
   * @var int
   */
  protected $cycles = 0;

  /**
   * The underlying coroutine associated with the task.
   *
   * @var Coroutine
   */
  protected $coroutine;

  /**
   * The name of the task’s current state. Printing it can be potentially useful for debugging.
   *
   * @var string
   */
  protected $state = null;

  /**
   * The result of a task.
   *
   * @var mixed
   */
  protected $result;
  protected $sendValue = null;

  /**
   * For operations needing to keep track of the calling `Task`.
   *
   * @var TaskInterface|FiberInterface
   */
  protected $caller = null;

  /**
   * @var TaskGroup
   */
  protected $taskGroup = null;

  /**
   *  @var ContextInterface
   */
  protected $withTask = null;

  protected $joined = false;

  protected $timer = null;

  protected $beforeFirstYield = true;

  /**
   * Exception raised by a task, if any.
   *
   * @var object
   */
  protected $error;
  protected $exception = null;

  /**
   * Use to store custom state, in relation to custom data.
   *
   * @var mixed
   */
  protected $customState;

  /**
   * Use to store custom data, mainly for `object`'s.
   * The object will get it's `close` method executed if present, on data reset.
   *
   * @var object
   */
  protected $customData;

  /**
   * Task type indicator.
   *
   * Currently using types of either `paralleled`, `awaited`, `stateless`, or `monitored`.
   *
   * @var string
   */
  protected $taskType = 'awaited';

  public function __construct($taskId, \Generator $coroutine)
  {
    $this->taskId = $taskId;
    $this->state = 'pending';
    $this->coroutine = Coroutine::create($coroutine);
  }

  public function close()
  {
    $object = $this->customData;
    if (\is_object($object)) {
      if (\method_exists($object, 'close'))
        $object->close();
      elseif ($object instanceof \UV && \uv_is_active($object))
        \uv_close($object);
    }

    $this->taskType = '';
    $this->taskId = null;
    $this->daemon = null;
    $this->cycles = 0;
    $this->coroutine = null;
    $this->caller = null;
    if ($this->taskGroup)
      $this->taskGroup->discard($this);

    $this->taskGroup = null;
    $this->withTask = null;
    $this->joined = null;
    $this->timer = null;
    $this->result = null;
    $this->sendValue = null;
    $this->beforeFirstYield = null;
    $this->error = null;
    $this->exception = null;
    $this->customState = null;
    unset($this->customData);
    $this->customData = null;
  }

  public function cyclesAdd(): void
  {
    $this->cycles++;
  }

  public function getCycles(): int
  {
    return $this->cycles;
  }

  public function taskId(): ?int
  {
    return $this->taskId;
  }

  public function taskType(string $type)
  {
    $this->taskType = $type;
  }

  public function sendValue($sendValue)
  {
    $this->sendValue = $sendValue;
  }

  public function setException($exception)
  {
    if ($exception instanceof CancelledError)
      $this->joined = true;

    $this->error = $this->exception = $exception;
  }

  public function setState(string $status)
  {
    $this->state = $status;
  }

  public function getState(): string
  {
    return $this->state;
  }

  public function customState($state = null)
  {
    $this->customState = $state;
  }

  public function customData($data = null)
  {
    $this->customData = $data;
  }

  public function getCustomState()
  {
    return $this->customState;
  }

  public function getCustomData()
  {
    return $this->customData;
  }

  public function exception(): ?\Throwable
  {
    return $this->error;
  }

  public function isCustomState($state): bool
  {
    return $this->customState === $state;
  }

  public function isParallel(): bool
  {
    return $this->taskType === 'paralleled';
  }

  public function isStateless(): bool
  {
    return $this->taskType === 'stateless';
  }

  public function isAsync(): bool
  {
    return $this->taskType === 'async';
  }

  public function isSelfCancellation(): bool
  {
    return $this->taskType === 'cancellation';
  }

  public function isFuture(): bool
  {
    return $this->state === 'process';
  }

  public function isErred(): bool
  {
    return $this->state === 'erred';
  }

  public function isPending(): bool
  {
    return $this->state === 'pending';
  }

  public function isCancelled(): bool
  {
    return $this->state === 'cancelled';
  }

  public function isSignaled(): bool
  {
    return $this->state === 'signaled';
  }

  public function isCompleted(): bool
  {
    return $this->state === 'completed';
  }

  public function isRescheduled(): bool
  {
    return ($this->state === 'rescheduled');
  }

  public function isFinished(): bool
  {
    $bool = ($this->coroutine instanceof \Generator) ? !$this->coroutine->valid() : true;
    if ($bool)
      $this->joined = true;

    return $bool;
  }

  public function isJoined(): bool
  {
    return $this->joined;
  }

  public function hasCaller(): bool
  {
    return $this->caller !== null;
  }

  public function setCaller($caller = null): void
  {
    $this->caller = $caller;
  }

  public function getCaller()
  {
    return $this->caller;
  }

  public function hasWith(): bool
  {
    return $this->withTask !== null;
  }

  public function getWith(): ?ContextInterface
  {
    return $this->withTask;
  }

  public function setWith(ContextInterface $context = null): TaskInterface
  {
    $this->withTask = $context;

    return $this;
  }

  public function getTimer()
  {
    return $this->timer;
  }

  public function setTimer($timer = null): TaskInterface
  {
    $this->timer = $timer;

    return $this;
  }

  public function setResult($value): void
  {
    $this->result = $value;
  }

  public function result()
  {
    if ($this->isCompleted()) {
      $result = $this->result;
      if ($this->customData instanceof FutureInterface)
        $data = $this->customData->getResult();
      $this->close();
      return isset($data) ? $data : $result;
    } elseif ($this->isCancelled() || $this->isErred() || $this->isSignaled()) {
      $error = $this->exception();
      if (empty($error))
        $error = new CancelledError("Internal operation stoppage, all Task data reset.");

      $message = $error->getMessage();
      $code = $error->getCode();
      $throwable = $error->getPrevious();
      $class = \get_class($error);
      $message = \str_replace(self::ERROR_MESSAGES, '', $message);
      $parent = \get_parent_class($error);
      if ($parent === false || \strpos($parent, 'Async\\') !== false || $parent === 'Exception')
        $this->close();

      return new $class($message, $code, $throwable);
    } else {
      $tid = $this->taskId();
      $this->close();
      if (!$this->isStateless())
        throw new InvalidStateError("{$tid}");

      return null;
    }
  }

  public function run()
  {
    if ($this->beforeFirstYield) {
      $this->beforeFirstYield = false;
      return ($this->coroutine instanceof \Generator)
        ? $this->coroutine->current()
        : null;
    } elseif ($this->exception) {
      $value = ($this->coroutine instanceof \Generator)
        ? $this->coroutine->throw($this->exception)
        : $this->exception;

      if (!$this->isStateless())
        $this->error = $this->exception;

      $this->exception = null;
      return $value;
    } else {
      $value = ($this->coroutine instanceof \Generator)
        ? $this->coroutine->send($this->sendValue)
        : $this->sendValue;

      if (!empty($value) && !$this->isStateless())
        $this->result = $value;

      $this->sendValue = null;
      return $value;
    }
  }

  public function hasGroup(): bool
  {
    return ($this->taskGroup !== null);
  }

  public function setGroup(TaskGroup $taskGroup = null): void
  {
    $this->taskGroup = $taskGroup;
  }

  public function getGroup(): ?TaskGroup
  {
    return $this->taskGroup;
  }

  public function discardGroup(): void
  {
    if ($this->taskGroup)
      $this->taskGroup->task_discard($this);
  }

  public function doneGroup(): void
  {
    if ($this->taskGroup)
      $this->taskGroup->task_done($this);
  }

  public function join()
  {
    yield $this->wait();
    $this->discardGroup();
    if ($this->error)
      \panic($this->error);
    else
      return $this->result;
  }

  public function wait()
  {
    while (!$this->isFinished())
      yield;
  }
}
