<?php

namespace Async;

use Async\TaskInterface;
use Async\ParallelInterface;
use Async\FiberInterface;
use Async\Spawn\Channeled;
use Async\Spawn\FutureInterface;
use Async\RuntimeException;
use Async\Spawn\Thread;

interface CoroutineInterface
{
  /**
   * Creates a new task (using the next free task id), wraps **Generator**, a `coroutine` into a `Task` and schedule its execution.
   * Returns the `Task` object/id.
   *
   * @see https://docs.python.org/3.10/library/asyncio-task.html#creating-tasks
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L331
   *
   * @param \Generator $coroutine
   * @param bool $isAsync should task type be set to a `async` function
   *
   * @return int task ID
   */
  public function createTask(\Generator $coroutine, bool $isAsync = false): int;

  /**
   * Add an new task into the running task queue.
   *
   * @param TaskInterface $task
   */
  public function schedule(TaskInterface $task);

  /**
   * Add a fiber instance (using the next free task id).
   * The fiber added has been wrapped into a coroutine for the `tasks map` list and schedules its execution.
   *
   * @param FiberInterface $fiber
   * @return int fiber task id
   */
  public function addFiber(FiberInterface $fiber);

  /**
   * Add an fiber instance into the running task queue.
   *
   * @param FiberInterface $fiber
   */
  public function scheduleFiber(FiberInterface $fiber);

  /**
   * Check if `object` is a FiberInterface instance.
   *
   * @param mixed $fiber
   * @return boolean
   */
  public function isFiber($object);

  /**
   * Performs a clean application shutdown, killing tasks/processes, and resetting all data, except **created** `async` functions.
   * - This function needs to be prefixed with `yield`
   *
   * Provide `$skipTask` incase called by an Signal Handler.
   *
   * @param int $skipTask - Defaults to the main parent task.
   * - The calling `$skipTask` task id will not get cancelled, the script execution will return to.
   * - Use `currentTask()` to retrieve caller's task id.
   */
  public function shutdown(?int $skipTask = 1);

  /**
   * Reset all `Coroutine` data.
   */
  public function close();

  /**
   * kill/remove an subprocess progress `realtime` ipc handler task.
   *
   * @param TaskInterface $task
   *
   * @return void
   */
  public function cancelProgress(TaskInterface $task);

  public function cancelledList(): ?array;

  /**
   * kill/remove an task using task id,
   * optionally pass custom cancel state for third party code integration.
   *
   * @see https://docs.python.org/3.9/library/asyncio-task.html#asyncio.Task.cancel
   * @source https://github.com/python/cpython/blob/bb0b5c12419b8fa657c96185d62212aea975f500/Lib/asyncio/tasks.py#L181
   *
   * @param int $tid
   * @param mixed $customState
   * @param string $errorMessage
   * @return bool
   *
   * @throws \InvalidArgumentException
   */
  public function cancelTask(int $tid, $customState = null, string $errorMessage = 'Invalid task ID!');

  /**
   * Start the main supervisor task.
   * Walk the task `queue` and execute the tasks.
   * If a task is finished it's dropped, otherwise rescheduled at the end of the `queue`.
   * - The `task` that's finish with any `result`, is moved into an `completed` task list.
   *
   * @see https://docs.python.org/3.7/library/asyncio-task.html#running-an-asyncio-program
   */
  public function run();

  /**
   * Set main supervisor task running state to `false`.
   * This allows the supervisor task to be recreated.
   *
   * @return void
   */
  public function ioStop();

  /**
   * Adds a read `event/socket/stream/file` descriptor to start
   * monitoring for read availability and invoke callback
   * once it's available for reading.
   *
   * @see https://docs.python.org/3.9/library/asyncio-eventloop.html#asyncio.loop.add_reader
   * @source https://github.com/python/cpython/blob/aa056ed472e9d0a79ea21784f6f5171d12a13f85/Lib/asyncio/selector_events.py#L257
   *
   * @param resource $stream
   * @param Fiber|Task|\Generator|Callable $task
   */
  public function addReader($stream, $task): CoroutineInterface;

  /**
   * Adds a write `event/socket/stream/file` descriptor to start
   * monitoring for write availability and invoke callback
   * once it's available for writing.
   *
   * @see https://docs.python.org/3.9/library/asyncio-eventloop.html#asyncio.loop.add_writer
   * @source https://github.com/python/cpython/blob/aa056ed472e9d0a79ea21784f6f5171d12a13f85/Lib/asyncio/selector_events.py#L294
   *
   * @param resource $stream
   * @param Fiber|Task|\Generator|Callable $task
   */
  public function addWriter($stream, $task): CoroutineInterface;

  /**
   * Stop monitoring the `event/socket/stream/file` descriptor for read availability.
   *
   * @see https://docs.python.org/3.9/library/asyncio-eventloop.html#asyncio.loop.remove_reader
   * @source https://github.com/python/cpython/blob/aa056ed472e9d0a79ea21784f6f5171d12a13f85/Lib/asyncio/selector_events.py#L273
   *
   * @param resource $stream
   */
  public function removeReader($stream): CoroutineInterface;

  /**
   * Stop monitoring the `event/socket/stream/file` descriptor for write availability.
   *
   * @see https://docs.python.org/3.9/library/asyncio-eventloop.html#asyncio.loop.remove_writer
   * @source https://github.com/python/cpython/blob/aa056ed472e9d0a79ea21784f6f5171d12a13f85/Lib/asyncio/selector_events.py#L310
   *
   * @param resource $stream
   */
  public function removeWriter($stream): CoroutineInterface;

  /**
   * Executes a function after x seconds.
   *
   * @param Task|\Generator|Callable $task
   * @param float $timeout
   * @param int $tid task ID
   */
  public function addTimeout($task = null, float $timeout = 0.0, int $tid = null);

  /**
   * Stop the execution of a `Task`'s timeout.
   *
   * @param TaskInterface $task
   * @return void
   */
  public function clearTimeout(TaskInterface $task): void;

  /**
   * Creates an object instance of the value which will signal to `Coroutine::create` that it's a return value.
   *
   *  - yield Coroutine::value("I'm a return value!");
   *
   * @internal
   *
   * @param mixed $value
   * @return ReturnValue
   */
  public static function value($value);

  /**
   * Creates an object instance of the value which will signal to `Coroutine::create` that it's a return value.
   *
   * @internal
   *
   * @param mixed $value
   * @return PlainValue
   */
  public static function plain($value);

  /**
   * Return the currently running/pending task list.
   *
   * @internal
   *
   * @return array<TaskInterface>|null
   */
  public function currentList(): ?array;

  /**
   * Return list of completed tasks, which the **results** has not been retrieved using `gather()`.
   *
   * @internal
   *
   * @return array<TaskInterface>|null
   */
  public function completedList(): ?array;


  /**
   * Check `Id` among **completed** `Task` list.
   *
   * @internal
   *
   * @param integer $tid
   * @return boolean
   */
  public function isCompleted(int $tid): bool;

  /**
   * Return an completed `task` by `Id`.
   *
   * @param integer $tid
   * @return FiberInterface|TaskInterface
   */
  public function getCompleted(int $tid);

  /**
   * Update _completed_ tasks list, and _current/running_ task, if cancelling the task.
   *
   * @internal
   *
   * @param integer $taskId a completed `task` Id.
   * @param array $completeList already **modified** completed task list.
   * @param callable|null $onClear optionally custom update function.
   * @param boolean $cancel should the `task` be **killed/removed**.
   * @param boolean $forceUpdate pull the completed list.
   * @return void
   */
  public function updateCompleted(
    int $taskId,
    array $completeList = [],
    ?callable $onClear = null,
    bool $cancel = false,
    bool $forceUpdate = false
  ): void;

  /**
   * Return the `Task` instance reference by `int` task id.
   *
   * @param int $taskId
   *
   * @internal
   *
   * @return null|TaskInterface|FiberInstance
   */
  public function getTask(?int $taskId = 0);

  public function isGroup(int $tid): bool;

  public function getGroup(): ?array;

  public function getGroupResult(int $tid);

  public function setGroupResult(int $tid, $value): void;

  /**
   * Add callable for parallel processing, in an separate php process
   *
   * @see https://docs.python.org/3.8/library/asyncio-subprocess.html#creating-subprocesses
   *
   * @param callable $callable
   * @param int|float|null $timeout The timeout in seconds or null to disable
   * @param bool $display set to show child process output
   * @param Channeled|resource|mixed|null $channel IPC communication to be pass to the underlying process standard input.
   *
   * @return FutureInterface
   */
  public function addFuture($callable, int $timeout = 0, bool $display = false, $channel = null): FutureInterface;

  /**
   * This will cause a _new thread_ to be **created** and **spawned** for the associated `Thread` object,
   * where its _internal_ task `queue` will begin to be processed.
   * - Add callable for parallel processing, in an separate `thread`
   *
   * @see https://docs.python.org/3.10/library/threading.html#module-threading
   *
   * @param string|int $tid Thread ID
   * @param callable $callable
   * @param mixed ...$args
   * @return Thread
   */
  public function addThread(int $tid, callable $callable, ...$args): Thread;

  /**
   * There are no **UV** file system operations/events pending.
   *
   * @return bool
   */
  public function isFsEmpty(): bool;

  /**
   * Add a UV file system operation to counter.
   *
   * @return void
   */
  public function fsAdd(): void;

  /**
   * Remove a UV file system operation from counter.
   *
   * @return void
   */
  public function fsRemove(): void;

  /**
   * There are no **UV** network operations pending.
   *
   * @return bool
   */
  public function isIoEmpty(): bool;

  /**
   * Add a UV network operation to counter.
   *
   * @return void
   */
  public function ioAdd(): void;

  /**
   * Remove a UV network operation from counter.
   *
   * @return void
   */
  public function ioRemove(): void;

  /**
   * Return the `Coroutine` class `libuv` loop handle, otherwise throw exception, if enabled and no driver found.
   *
   * @return null|\UVLoop
   * @throws RuntimeException
   */
  public function getUV(): ?\UVLoop;

  /**
   * Is `libuv` features available.
   */
  public function isUv(): bool;

  /**
   * Turn **On** _manual_ `main supervisor task` execution, pause *automatic*.
   *
   * @return void
   */
  public function futureOn(): void;

  /**
   * Turn **Off** _manual_ `main supervisor task` execution, resume *automatic*.
   *
   * @return void
   */
  public function futureOff(): void;

  /**
   * Setup to use `libuv` features, reset/recreate **UV** handle, enable/disable.
   * - This will `stop` and `delete` any current **UV** event loop instance.
   * - This will also reset `FileSystem::setup` and **symplely/spawn** `Spawn::setup`
   * with the same config.
   *
   * @param bool $useUvLoop
   *
   * @return CoroutineInterface
   */
  public function setup(bool $useUvLoop = true): CoroutineInterface;

  /**
   * The `Parallel` class pool future instance.
   *
   * @return ParallelInterface
   */
  public function getParallel(): ParallelInterface;

  /**
   * The `Thread` class instance.
   *
   * @return Thread
   */
  public function getThread(): Thread;

  /**
   * Is `libuv` features available and the system is **Linux**.
   *
   * `Note:` Network related `libuv` features are currently broken on **Windows**.
   *
   * @return bool
   */
  public function isUvActive(): bool;

  /**
   * Check if `PCNTL` extension is available for asynchronous signaling.
   *
   * @return bool
   */
  public function isPcntl(): bool;

  /**
   * Run all `tasks` in the queue.
   *
   * If there are none, no I/O, timers or etc... the script/application will exit immediately.
   *
   * @internal
   *
   * @param bool $isReturn - a conditional return or a flag for additional processing after one loop tick.
   */
  public function execute($isReturn = false);

  /**
   * Execute/schedule the retrieved `$task`.
   *
   * @internal
   *
   * @param Task|\Generator|Callable $task
   * @param mixed $parameters
   */
  public function executeTask($task, $parameters = null);

  /**
   * Create and manage a stack of nested coroutine calls. This allows turning
   * regular functions/methods into sub-coroutines just by yielding them.
   *
   *  - $value = (yield functions/methods($foo, $bar));
   *
   * @internal
   *
   * @param \Generator $gen
   */
  public static function create(\Generator $gen);

  /**
   * Register a listener to be notified when a signal has been caught by this process.
   *
   * This is useful to catch user interrupt signals or shutdown signals from the `OS`.
   *
   * The listener callback function MUST be able to accept a single parameter,
   * the signal added by this method or you MAY use a function which
   * has no parameters at all.
   *
   * **Note: A listener can only be added once to the same signal, any
   * attempts to add it more than once will be ignored.**
   *
   * @param int $signal
   * @param Task|\Generator|Callable $listener
   */
  public function addSignal($signal, $listener);

  /**
   * Removes a previously added signal listener.
   *
   * Any attempts to remove listeners that aren't registered will be ignored.
   *
   * @param int $signal
   * @param Task|\Generator|Callable $listener
   */
  public function removeSignal($signal, $listener);
}
