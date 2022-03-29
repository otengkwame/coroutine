<?php

namespace Async;

use Async\Misc\TaskGroup;
use Async\Misc\ContextInterface;

/**
 * Provides a way for a task to interrupt itself and pass control back
 * to the scheduler, and allowing some other task to run.
 */
interface TaskInterface
{
  /**
   * The task’s id.
   *
   * @return integer|null
   */
  public function taskId(): ?int;

  /**
   * Add to counter of the cycles the task has run.
   *
   * @return void
   */
  public function cyclesAdd(): void;

  /**
   * Return the number of times the scheduled task has run.
   *
   * @return int
   */
  public function getCycles(): int;

  /**
   * Set Task type, currently either `paralleled`, `async`, `awaited`,
   * `stateless`, or `monitored`.
   *
   * @param string $type
   *
   * @return void
   *
   * @internal
   */
  public function taskType(string $type);

  /**
   * @param mixed $sendValue
   *
   * @return void
   *
   * @internal
   */
  public function sendValue($sendValue);

  /**
   * @param string $status
   *
   * @return void
   *
   * @internal
   */
  public function setState(string $status);

  /**
   * Return task current status state.
   *
   * @return string
   *
   * @internal
   */
  public function getState(): string;

  /**
   * Start the execution of the callers code, passing any `value` or `exception` back and forth.
   *
   * @return mixed
   */
  public function run();

  /**
   * Reset all `Task` data, and call `close` on any related stored `object` classes.
   */
  public function close();

  /**
   * Store custom state of the task.
   */
  public function customState($state = null);

  /**
   * Store custom `object` data of the task.
   */
  public function customData($data = null);

  /**
   * Return the stored custom state of the task.
   */
  public function getCustomState();

  /**
   * Return the stored custom data of the task.
   */
  public function getCustomData();

  /**
   * A flag that indicates custom state is as requested.
   *
   * @return bool
   */
  public function isCustomState($state): bool;

  /**
   * A flag that indicates the task is parallel child future.
   *
   * @return bool
   */
  public function isParallel(): bool;

  /**
   * A flag that indicates the task is `stateless`, nothing will be stored for later.
   * - All memory is freed, not in completed task list, and no results retained.
   *
   * @return bool
   */
  public function isStateless(): bool;

  /**
   * A flag that indicates the task is a `async` created function, a **closure**.
   *
   * @return bool
   */
  public function isAsync(): bool;

  /**
   * A flag that indicates the task is doing a `self` cancellation.
   *
   * @return bool
   */
  public function isSelfCancellation(): bool;

  /**
   * A flag that indicates whether or not the `future` chid-process task has started.
   *
   * @return bool
   */
  public function isFuture(): bool;

  /**
   * A flag that indicates whether or not the task has an error.
   *
   * @return bool
   */
  public function isErred(): bool;

  /**
   * A flag that indicates whether or not the task has started.
   *
   * @return bool
   */
  public function isPending(): bool;

  /**
   * A flag that indicates whether or not the task was cancelled.
   *
   * @return bool
   */
  public function isCancelled(): bool;

  /**
   * A flag that indicates whether or not the a `parallel/future` task received a kill signal.
   *
   * @return bool
   */
  public function isSignaled(): bool;

  /**
   * A flag that indicates whether or not the task has run to completion.
   *
   * @return bool
   */
  public function isCompleted(): bool;

  /**
   * A flag that indicates whether or not the task has been **scheduled** to run again.
   *
   * @return boolean
   */
  public function isRescheduled(): bool;

  /**
   * Returns false if the **generator/coroutine** has been closed, true otherwise.
   *
   * @return boolean
   */
  public function isFinished(): bool;

  public function isJoined(): bool;

  /**
   * Check `Task` has another _task_ to **schedule** on _completion/termination_.
   *
   * @return bool
   *
   * @internal
   */
  public function hasCaller(): bool;

  /**
   * Set the task for scheduling a return to, from a `join()` or _function_ now controlling.
   *
   * @param TaskInterface|FiberInterface|null $caller
   * @return void
   *
   * @internal
   */
  public function setCaller($caller = null): void;

  /**
   * Get the `task` that a `join()` or function _no longer_ in control of.
   *
   * @return TaskInterface|FiberInterface
   *
   * @internal
   */
  public function getCaller();

  /**
   * Check task is _actively_ being used by `async_with` or `with`.
   *
   * @return boolean
   */
  public function hasWith(): bool;

  /**
   * Get the context manager instance used by `async_with` or `with`.
   *
   * @return ContextInterface|null
   */
  public function getWith(): ?ContextInterface;

  /**
   * Set the context manager instance initialized by `async_with` or `with`.
   *
   * @param ContextInterface|null $context
   * @return self
   */
  public function setWith(ContextInterface $context = null): TaskInterface;

  /**
   * Check for a `TaskGroup` task.
   *
   * @return boolean
   */
  public function hasGroup(): bool;

  /**
   * Set to containing task group.
   *
   * @param TaskGroup|null $taskGroup
   * @return void
   */
  public function setGroup(TaskGroup $taskGroup = null): void;

  /**
   * Containing task group (if any).
   *
   * @return TaskGroup|null
   */
  public function getGroup(): ?TaskGroup;

  /**
   * Mark task completed in task group.
   *
   * @return void
   */
  public function doneGroup(): void;

  /**
   * Discard task from the `TaskGroup`.
   *
   * @return void
   */
  public function discardGroup(): void;

  /**
   * Pending timeout (if any).
   *
   * @return int|\UVTimer
   */
  public function getTimer();

  /**
   * Store/clear the timeout that was setup.
   *
   * @param int|\UVTimer|null $timer
   * @return self
   */
  public function setTimer($timer = null): TaskInterface;

  /**
   * Manually set the result for `result()` when called.
   *
   * @param mixed $value
   * @return void
   */
  public function setResult($value): void;

  /**
   * Return the result of the Task.
   *
   * - If the Task is done, the result of the wrapped coroutine is returned
   * (or if the coroutine raised an exception, that exception is re-raised.)
   *
   * - If the Task has been cancelled, this method raises a `CancelledError` exception.
   *
   * - If the Task’s result isn’t yet available, this method raises a `InvalidStateError` exception.
   *
   * @see https://docs.python.org/3/library/asyncio-task.html#asyncio.Task.result
   */
  public function result();

  /**
   * Wait for a task to terminate.
   * Returns the return value (if any) or throw a `Exception` if the task crashed with an exception.
   * - This function needs to be prefixed with `yield`
   *
   * @return mixed
   * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/task.py#L177
   */
  public function join();

  /**
   * Wait for a task to terminate. Does not return any value.
   *
   * @return void
   */
  public function wait();

  /**
   * Mark the task as done and set an exception.
   *
   * @param \Throwable $exception
   *
   * @return void
   *
   * @internal
   */
  public function setException($exception);

  /**
   * Return the exception of the Task.
   *
   * - If the wrapped coroutine raised an exception that exception is returned.
   *
   * - If the wrapped coroutine returned normally this method returns `null`.
   *
   * - If the Task has been cancelled, this method raises a `CancelledError` exception.
   *
   * - If the Task isn’t done yet, this method raises an `InvalidStateError` exception.
   *
   * @see https://docs.python.org/3/library/asyncio-task.html#asyncio.Task.exception
   */
  public function exception(): ?\Throwable;
}
