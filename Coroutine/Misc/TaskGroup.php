<?php

declare(strict_types=1);

namespace Async\Misc;

use Async\TaskInterface;
use Async\RuntimeError;
use Async\Misc\ContextAsyncIterator;

/**
 * A `TaskGroup` represents a collection of managed `tasks`. A group can be used to ensure that all tasks terminate together.
 *
 *```php
 *   A TaskGroup can be created from existing tasks.  For example:
 *
 *       $t1 = yield spawner(coro1);
 *       $t2 = yield spawner(coro2);
 *       $t3 = yield spawner(coro3);
 *
 *       $g = async_with(new TaskGroup([$t1, $t2, $t3]));
 *             // ... other `$g` methods.
 *       yield ending($g);
 *          ...
 *
 *   Alternatively, tasks can be spawned into a task group.
 *
 *       $g = async_with(new TaskGroup(wait));
 *          yield $g->spawn(coro1);
 *          yield $g->spawn(coro2);
 *          yield $g->spawn(coro3);
 *
 *         yield ending($g);
 *```
 *
 * Task groups are often used to gather results. If any managed task exits with an error, all remaining tasks
 * are cancelled.  The handling of task-related errors takes place when the `result` of a task group is analyzed.
 *
 *Task groups are often used to gather results. The following properties are useful:
 *
 *```php
 *     $g = async_with(task_group());
 *         ...
 *      yield ending($g);
 *
 *    print($g->result);    # Result of the first task to exit
 *    print($g->results);   # List of all results computed
 *```
 *
 *  Note: Both of these properties may **Throw** an `exception` if a task
 *  exited in error.
 *
 * The `add_task()` method can be used to add an already existing task to a group.  Calling
 * `join_task(TaskId)` or `await(join, TaskId)` on a specific task removes it from a group. For example:
 *
 * ```php
 *       $g = async_with(new TaskGroup());
 *       $t1 = yield $g->spawn(coro1);
 *           ...
 *            # Either of these removes $t1 from the group
 *       yield await(join, $t1);
 *            # Or
 *       yield join_task($t1);
 *           ...
 *       yield ending($g);
 *```
 *
 *    Normally, a task group is used as a context manager.  This
 *   doesn't have to be the case.  You could write code like this:
 *
 *```php
 *      $g = new TaskGroup();
 *        try{
 *            yield $g->spawn(coro1);
 *            yield $g->spawn(coro2);
 *            ...
 *        } finally {
 *            yield $g->join();
 *        }
 *```
 *
 *   This might be more useful for more persistent or long-lived
 *   task groups.
 *
 *    If any managed task exits with an error, all remaining tasks
 *    are cancelled.  The handling of task-related errors takes place when
 *    the result of a task group is analyzed.  For example:
 *
 *```php
 *        $g = async_with(task_group([], 'any'));
 *            yield $g->spawn(coro1);
 *            yield $g->spawn(coro2);
 *            ...
 *        try {
 *            $result = $g->result      # Errors reported here
 *        } catch (\Exception $e) {
 *           print("FAILED:", $e->getMessage());
 *        }
 *```
 *
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/task.py#L271
 */
final class TaskGroup extends ContextAsyncIterator
{
  /**
   * All running tasks
   *
   * @var array[int => TaskInterface]
   */
  protected $running = [];

  /**
   * All finished tasks
   *
   * @var array<TaskInterface>[]
   */
  protected $finished = [];

  protected $joined = false;

  /**
   * Wait policy
   *
   * @var string
   */
  protected $wait = 'all';

  /**
   * First completed task result
   *
   * @var mixed
   */
  protected $completed = null;

  public function __destruct()
  {
    if (!$this->exit)
      $this->__exit();

    $this->wait = null;
    $this->joined = null;
    $this->finished = null;
    $this->running = null;
    $this->completed = null;
  }

  /**
   * @param array<int> $tasks To monitor and collect results.
   * @param string $wait When used as a context manager, will wait until
   * all contained tasks exit before moving on. The optional wait argument
   * specifies a strategy.
   *
   * If `wait=all` (the default), a task group waits for all tasks to exit.
   *
   * If `wait=any`, the group waits for the first task to exit.
   *
   * If `wait=object`, the group waits for the first task to return a non-None result.
   *
   * If `wait=None`, the group immediately cancels all running tasks.
   */
  public function __construct(array $tasks = [], string $wait = 'all')
  {
    $coroutine = \coroutine();
    $this->wait = $wait;
    foreach ($tasks as $tid) {
      $found = false;
      $task = $coroutine->getTask($tid);
      if ($coroutine->isCompleted($tid)) {
        $found = true;
        $this->task_done($coroutine->getCompleted($tid));
      } elseif (!$task instanceof TaskInterface) {
        \panic(new RuntimeError("Task {$tid} is invalid!"));
      } elseif ($task->hasGroup()) {
        \panic(new RuntimeError("Task already assigned to a task group"));
      }

      if (!$found) {
        $task->setGroup($this);
        $this->running[$tid] = $task;
      }
    }
  }

  /**
   * Add an already existing task to the group.
   *
   * @return void
   */
  public function add_task(int $tid)
  {
    $coroutine = \coroutine();
    $task = $coroutine->getTask($tid);
    if ($task instanceof TaskInterface && $task->hasGroup()) {
      \panic(new RuntimeError('Task already assigned to a task group'));
    } elseif ($this->joined) {
      \panic(new RuntimeError('TaskGroup already joined'));
    }

    if ($coroutine->isGroup($tid)) {
      $result = $coroutine->getGroupResult($tid);
      $this->finished[$tid] = $result;
      $coroutine->setGroupResult($tid, $result);
    } elseif ($coroutine->isCompleted($tid)) {
      $this->task_done($coroutine->getCompleted($tid));
    } elseif ($task instanceof TaskInterface) {
      $task->setGroup($this);
      $this->running[$tid] = $task;
    }
  }

  /**
   * Spawn a new task into the task group.
   * - This function needs to be prefixed with `yield`
   *
   * @param Generator|callable|string $awaitableFunction - `async`, a coroutine, or a function to make `awaitable`
   * @param mixed ...$args - if **$awaitableFunction** is `Generator`, $args can hold `customState`, and `customData`
   * - for third party code integration.
   *
   * @return int $task id
   */
  public function spawn($awaitableFunction, ...$args)
  {
    if ($this->joined)
      \panic(new RuntimeError('TaskGroup already joined'));

    $tid = yield \spawner($awaitableFunction, ...$args);
    $this->add_task($tid);

    return $tid;
  }

  /**
   * Wait for the next task to finish and return it. This removes it from the group.
   * - This function needs to be prefixed with `yield`
   *
   * @return mixed `int`|`null` a task Id instance
   */
  public function next_done()
  {
    $tid = null;
    if ($this->finished) {
      $tid = \array_key_first($this->finished);
      unset($this->finished[$tid]);
      yield;
    } elseif (\count($this->running) > 0) {
      $task = \array_shift($this->running);
      if ($task instanceof TaskInterface) {
        yield $task->wait();
        $task->setGroup();
        $tid = $task->taskId();
      }
    }

    return $tid;
  }

  /**
   * Return the result of the next task that finishes.
   * Note: if task terminated via exception, that exception is raised here.
   * - This function needs to be prefixed with `yield`
   *
   * @return mixed
   */
  public function next_result()
  {
    $tid = yield $this->next_done();
    if ($tid)
      return \result_for($tid);

    \panic(new RuntimeError('No task available'));
  }

  /**
   * Cancel all remaining tasks.
   * Tasks are removed from the task group when explicitly cancelled.
   * - This function needs to be prefixed with `yield`
   *
   * @return void
   */
  public function cancel_remaining()
  {
    foreach ($this->running as $tid => $task) {
      yield \cancel_task($tid);
      $this->task_discard($task);
    }
  }

  /**
   * Discards a task from the TaskGroup.
   * Called implicitly if a task is explicitly joined while under supervision.
   * @param TaskInterface $task
   *
   * @return void
   */
  public function task_discard(TaskInterface $task)
  {
    $tid = $task->taskId();
    if (isset($this->finished[$tid]))
      unset($this->finished[$tid]);

    if (isset($this->running[$tid]))
      unset($this->running[$tid]);

    $task->setGroup();
  }

  /**
   * Triggered on task completion.
   *
   * @param TaskInterface $task
   * @return void
   */
  public function task_done(TaskInterface $task, bool $exception = false, \Throwable $error = null)
  {
    $coroutine = \coroutine();
    $tid = $task->taskId();
    if (isset($this->running[$tid]))
      unset($this->running[$tid]);

    if ($exception)
      $result = empty($error) ? $task->exception() : $error;
    else
      $result = $task->result();

    $this->finished[$tid] = $result;
    $coroutine->setGroupResult($tid, $result);
    $coroutine->updateCompleted($tid);
  }

  /**
   * Return first completed task Id.
   *
   * @return integer|null
   */
  public function completed(): ?int
  {
    if (!\is_null($this->completed))
      return \array_key_first($this->completed);

    \panic(new RuntimeError("No task successfully completed"));
  }

  /**
   * Returns the result of the `first` completed task.
   *
   * @return mixed
   */
  public function result()
  {
    if (!$this->joined)
      \panic(new RuntimeError("Task group not yet terminated"));

    if (!$this->completed)
      \panic(new RuntimeError("No task successfully completed"));

    $tid = \array_key_first($this->completed);
    if ($this->completed[$tid] instanceof \Throwable)
      throw $this->completed[$tid];

    return $this->completed[$tid];
  }

  /**
   * Returns the exception of the first completed task.
   *
   * @return null|\Throwable
   */
  public function exception(): ?\Throwable
  {
    if (!$this->joined)
      \panic(new RuntimeError("Task group not yet terminated"));

    if ($this->completed) {
      $tid = \array_key_first($this->completed);
      if (isset($this->completed[$tid]) && $this->completed[$tid] instanceof \Throwable)
        return $this->completed[$tid];
    }

    return null;
  }

  /**
   * Returns `all` task results (in task creation order)
   *
   * @return array[ task id => result ]
   */
  public function results()
  {
    if (!$this->joined)
      \panic(new RuntimeError("Task group not yet terminated"));

    $results = [];
    foreach ($this->finished as $tid => $value) {
      if (!$value instanceof \Throwable)
        $results[$tid] = $value;
    }

    return $results;
  }

  /**
   * Returns `all` exceptions in Task Group.
   *
   * @return array[ task id => exceptions ]
   */
  public function exceptions()
  {
    if (!$this->joined)
      \panic(new RuntimeError("Task group not yet terminated"));

    $exceptions = [];
    foreach ($this->finished as $tid => $exception) {
      if ($exception instanceof \Throwable)
        $exceptions[$tid] = $exception;
    }

    return $exceptions;
  }

  /**
   * Wait for tasks in a task group to terminate according to the wait policy set for the group.
   * - This function needs to be prefixed with `yield`
   *
   * @return bool
   */
  public function join()
  {
    $coroutine = \coroutine();
    try {
      // We wait for no-one. Tasks get cancelled on return.
      if ($this->wait == 'None')
        return;

      foreach ($this->running as $tid => $task) {
        if (!$coroutine->getTask($tid) instanceof TaskInterface)
          unset($this->running[$tid]);
      }

      # If nothing is finished, we wait for something to complete
      while (\count($this->running) > 0) {
        // Examine all currently finished tasks
        foreach ($this->finished as $tid => $value) {
          // Check if it's the first completed task
          if ($this->completed === null) {
            // For wait=object, the $this->completed attribute is the first non-None result
            if (!(($this->wait == 'object') && (!$value instanceof \Throwable) && ($value === null)))
              $this->completed[$tid] = $value;
          }

          // What happens next depends on the wait and error handling policies
          if (($value instanceof \Throwable)
            || ($this->wait == 'any')
            || (($this->wait == 'object') && ($value  != null))
          ) {
            return;
          }
        }

        if ($this->wait == 'Error')
          return;

        yield;
      }
    } finally {
      if ($this->wait == 'Error' && $this->completed === null) {
        $tid = yield \current_task();
        if (isset($this->running[$tid])) {
          $this->completed[$tid] = $this->finished[$tid] = $this->error;
          $coroutine->setGroupResult($tid, $this->completed[$tid]);
        }
      }

      // Task groups guarantee all tasks cancelled/terminated upon join()
      foreach ($this->running as $tid => $task) {
        if ($coroutine->getTask($tid) instanceof TaskInterface) {
          if ($this->completed === null && $task->exception() instanceof \Throwable) {
            $this->completed[$tid] = $task->exception();
            $coroutine->setGroupResult($tid, $this->completed[$tid]);
          } elseif (!$coroutine->isGroup($tid) && $task->exception() instanceof \Throwable) {
            $coroutine->setGroupResult($tid, $task->exception());
          }

          // Only schedule throws when cancelled by errors, not when completed successful
          if (
            $this->wait == 'any'
            && $this->completed !== null
            && !$this->completed[$this->completed()] instanceof \Throwable
          )
            $coroutine->cancelTask($tid);
          else
            yield \cancel_task($tid, null, 'Invalid task ID!', true);

          $this->task_discard($task);
        }
      }

      $this->joined = true;
      return;
    }
  }

  public function __invoke()
  {
    if (!$this->withSet) {
      yield $this->withSet();
    }

    if (!$this->enter) {
      return $this->__enter();
    } elseif (!$this->exit) {
      try {
        return yield $this->join();
      } finally {
        if (!$this->exit)
          $this->__exit();
      }
    }
  }

  public function __exit(\Throwable $type = null)
  {
    if (!empty($type)) {
      $this->error = $type;
    }

    if ($type) {
      $this->wait = 'Error';
      yield $this->join();
      $this->error = null;
    }

    $this->exit = true;
    parent::__destruct();
  }

  public function current(): \Generator
  {
    return $this->next_done();
  }

  public function valid(): bool
  {
    return !\is_null(\array_key_first($this->finished)) || !\is_null(\array_key_first($this->running));
  }
}
