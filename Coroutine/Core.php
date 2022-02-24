<?php

declare(strict_types=1);

use Async\CancelledError;
use Async\Defer;
use Async\Kernel;
use Async\Channel;
use Async\Co;
use Async\Coroutine;
use Async\CoroutineInterface;
use Async\InvalidStateError;
use Async\Misc\AsyncIterator;
use Async\Panic;
use Async\Panicking;
use Async\TaskInterface;
use Async\Misc\TaskGroup;
use Async\Misc\ContextInterface;
use Async\Misc\Semaphore;
use Psr\Container\ContainerInterface;

use function Async\Worker\awaitable_future;

if (!\function_exists('coroutine_run')) {

  if (!\defined('None'))
    \define('None', null);

  if (!\defined('IS_PHP81'))
    \define('IS_PHP81', ((float) \phpversion() >= 8.1));

  /**
   * A construct to _return_ an **associative** `array`, a dictionary.
   *
   * @param string|int $key
   * @param mixed $value
   * @return array
   */
  function kv($key, $value): array
  {
    return [$key => $value];
  }

  /**
   * A construct to _return_ an **associative** `array`, a dictionary.
   */
  \define('kv', 'kv');

  /**
   * Returns a random float between two numbers.
   *
   * Works similar to Python's `random.uniform()`
   * @see https://docs.python.org/3/library/random.html#random.uniform
   *
   * @param int $min
   * @param int $max
   * @return float
   */
  function random_uniform($min, $max)
  {
    return ($min + \lcg_value() * (\abs($max - $min)));
  }

  /**
   * Return the value (in fractional seconds) of a performance counter, i.e. a clock with the highest
   * available resolution to measure a short duration. Using either `hrtime` or system's `microtime`.
   *
   * @param string $tag
   * - A reference point used to set, to get the difference between the results of consecutive calls.
   * - Will be cleared/unset on the next consecutive call.
   *
   * @return float|null
   *
   * @see https://docs.python.org/3/library/time.html#time.perf_counter
   * @see https://nodejs.org/docs/latest-v11.x/api/console.html#console_console_time_label
   */
  function timer_for(string $tag = 'perf_counter')
  {
    if (Co::hasTiming($tag)) {
      $perf_counter = Co::getTiming($tag);
      Co::clearTiming($tag);
      return (float) (Co::hasTiming('hrtime') ? (\hrtime(true) / 1e+9) - $perf_counter : \microtime(true) - $perf_counter);
    }

    Co::setTiming($tag, (float) (Co::hasTiming('hrtime') ? \hrtime(true) / 1e+9 : \microtime(true)));
  }

  /**
   * Makes an resolvable function from `label` name that's callable with `coroutine_run()`, `go()`,
   * `await()`, `away()`, `spawner()` and inturn calls **create_task()**.
   * The passed in `function` is wrapped to be `awaitAble`. The `label` will be `Define()` and make that _name_ a **global** `constant`.
   *
   * - This will store a closure in `Co` static class with supplied `label` name as key.
   * @see https://docs.python.org/3.10/reference/compound_stmts.html#async-def
   *
   * @param string $label
   * @param callable $function
   * @return void
   * @throws Panic — if the **named** `label` function already exists.
   */
  function async(string $label, callable $function): void
  {
    if (!\defined("$label"))
      \define("$label", "$label");

    Kernel::async($label, $function);
  }

  /**
   * Allows convenient iteration over asynchronous `Iterator`.
   * This will obtain `task` results in the order that they complete, as they complete.
   * - Only `current()`, and `valid()` _methods_ SHOULD BE *implemented* in `Iterator` .
   * - This function needs to be prefixed with `yield`
   *
   * @param AsyncIterator $task A `task` producing _results_ in **chunks**.
   * @param \Closure $as Will **receive** _chunk_ of a _task_ `result` for processing.
   * @return void
   * @see https://docs.python.org/3/reference/compound_stmts.html#the-async-for-statement
   * @see https://docs.python.org/3.10/reference/expressions.html#asynchronous-generator-functions
   */
  function async_for(AsyncIterator $task, \Closure $as)
  {
    return Kernel::asyncFor($task, $as);
  }

  /**
   * Allows convenient iteration over asynchronous `Iterator`.
   * This will obtain `task` results in the order that they complete, as they complete.
   */
  \define('async_for', 'async_for');

  /**
   * Begins an asynchronous context manager that is able to suspend execution in its `__enter()` and `__exit()` methods.
   *  It is a **Error** to use `async_with` outside of an `async` function.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://book.pythontips.com/en/latest/context_managers.html
   *
   * @param ContextInterface|resource $context
   * @param ContainerInterface|object|ContextInterface|null $object
   * @param array[] $options
   * @return ContextInterface
   * @throws Panic if no context instance, or `__enter()` method does not return `true`.
   */
  function async_with($context = null, $other = null, array $options = [])
  {
    return Kernel::asyncWith($context, $other, $options);
  }

  /**
   * Begins an asynchronous context manager that is able to suspend execution in its `__enter()` and `__exit()` methods.
   */
  \define('async_with', 'async_with');

  /**
   * Begins an asynchronous context manager that is able to suspend execution in its `__enter()` and `__exit()` methods.
   * It is a **Error** to use `with` outside of an `async` function.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://book.pythontips.com/en/latest/context_managers.html
   *
   * @param ContextInterface|resource $context
   * @param \Closure $as - Will receive a **ContextInterface** instance, when finish will execute `__exit()`, the `ending()` function.
   * @return ContextInterface
   * @throws Panic if no context instance, or `__enter()` method does not return `true`.
   */
  function with($context = null, \Closure $as = null)
  {
    return Kernel::with($context, $as);
  }

  /**
   * Begins an asynchronous context manager that is able to suspend execution in its `__enter()` and `__exit()` methods.
   */
  \define('with', 'with');

  /**
   * Ends an `async_with()` or `with()` **Context** block, and executes `__exit()` method and any closing _routine_.
   * - This function needs to be prefixed with `yield`
   *
   * @param ContextInterface $context
   * @return void
   * @throws Exception if any `Context` _managed_ code **error's**.
   * @throws Panic if `__exit()` method does not return `true`.
   */
  function ending(ContextInterface $context)
  {
    try {
      if ($context() instanceof \Generator)
        yield $context();
    } finally {
      if ($context instanceof Semaphore) {
        yield $context->release();
      }

      if (!$context->exited())
        $context->__exit(new Panic('Context block failed to exit!'));
    }
  }

  /**
   * Ends an `async_with` or `with` **Context** block, and executes `__exit()` method and any closing _routine_.
   */
  \define('ending', 'ending');

  /**
   * A `TaskGroup` represents a collection of managed `tasks`. A group can be used to ensure that all tasks terminate together.
   *
   * @param array $tasks To monitor and collect results.
   * @param string $wait - When used as a context manager, will wait until
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
   * @return TaskGroup
   * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/task.py#L271
   */
  function task_group(array $tasks = [], $wait = 'all'): TaskGroup
  {
    return new TaskGroup($tasks, $wait);
  }

  /**
   * A `TaskGroup` represents a collection of managed `tasks`.
   * A group can be used to ensure that all tasks terminate together.
   */
  \define('task_group', 'task_group');

  /**
   * Returns `all` task _results_ (in `Task Id` creation order).
   *
   * @return array
   */
  function group_results(TaskGroup $object)
  {
    return $object->results();
  }

  /**
   * Returns `all` task _results_ (in `Task Id` creation order).
   */
  \define('group_results', 'group_results');

  /**
   * Returns _result_ of the `first` task to exit.
   *
   * @return mixed
   */
  function group_result(TaskGroup $object)
  {
    return $object->result();
  }

  /**
   * Returns _result_ of the `first` task to exit.
   */
  \define('group_result', 'group_result');

  /**
   * Returns the _result_ of a completed `task`.
   *
   * @param integer $tid task id instance
   * @return mixed
   * @throws Exception|Error if _task_ `erred`.
   * @throws InvalidStateError if still `running`, not terminated.
   */
  function result_for(int $tid)
  {
    return Kernel::resultFor($tid);
  }

  /**
   * Returns the _result_ of a completed `task`.
   */
  \define('result_for', 'result_for');

  /**
   * Returns the _exception_ of a `task`.
   *
   * @param integer $tid task id instance
   * @return null|Throwable
   * @throws InvalidStateError if _task_ still `running`, not terminated.
   */
  function exception_for(int $tid): ?\Throwable
  {
    return Kernel::exceptionFor($tid);
  }

  /**
   * Returns the _exception_ of a `task`.
   */
  \define('exception_for', 'exception_for');

  /**
   * Check _task_, returns `true` if cancelled.
   *
   * @param integer $tid task id instance
   * @return bool
   */
  function is_cancelled(int $tid): bool
  {
    return isset(\coroutine()->cancelledList()[$tid]);
  }

  /**
   * Check _task_, returns `true` if _currently_, **actively** being cancelled.
   *
   * @param integer $tid task id instance
   * @return bool
   */
  function is_cancelling(int $tid): bool
  {
    $task = \coroutine()->getTask($tid);
    if ($task instanceof TaskInterface  && $task->hasGroup() && $task->getGroup()->isWith())
      return $task->getGroup()->withTask()->exception() instanceof CancelledError;

    return $task instanceof TaskInterface  && $task->exception() instanceof CancelledError;
  }

  /**
   * Check _task_, returns `true` if terminated, not `running`.
   *
   * @param integer $tid task id instance
   * @return bool
   */
  function is_terminated(int $tid): bool
  {
    $coroutine = \coroutine();
    if ($coroutine->getTask($tid))
      return $coroutine->getTask($tid)->isFinished();
    elseif ($coroutine->isCompleted($tid))
      return $coroutine->getCompleted($tid)->isFinished();

    return false;
  }

  /**
   * Check _task_, returns `true` if joined, _execution status_ has changed.
   *
   * @param integer $tid task id instance
   * @return bool
   */
  function is_joined(int $tid): bool
  {
    $coroutine = \coroutine();
    if ($coroutine->getTask($tid))
      return $coroutine->getTask($tid)->isJoined();
    elseif ($coroutine->isCompleted($tid))
      return $coroutine->getCompleted($tid)->isJoined();

    return false;
  }

  /**
   * Run `callable(*args)` in a separate **process** and returns the result. In the event of _cancellation_,
   * the worker _process_ and the associated `task` is immediately terminated. This results in a `SIGKILL`
   * signal being sent to the worker _process_.
   *
   * The given `callable` executes in an entirely independent **PHP interpreter** and there is no shared global state.
   * - This function needs to be prefixed with `yield`
   *
   * @param callable $callable
   * @param mixed ...$args
   * @return mixed
   * @see https://curio.readthedocs.io/en/latest/reference.html?highlight=TaskError#run_in_process
   */
  function run_in_process(callable $callable, ...$args)
  {
    $process = function () use ($callable, $args) {
      return $callable(...$args);
    };

    return awaitable_future(function () use ($process) {
      return Kernel::addFuture($process, 0, false, null, null, \SIGKILL, null, 'signaling');
    });
  }

  /**
   * Run `callable(*args)` in a separate **process** and returns the result.
   * In the event of _cancellation_, the worker _process_ and the associated `task` is immediately terminated.
   * This results in a `SIGKILL` signal being sent to the worker _process_.
   */
  \define('run_in_process', 'run_in_process');

  /**
   * This function will `pause` and execute the `label` function, with `arguments`,
   * only functions created with `async`, or some **reserved**,  or
   * a `PHP` builtin callable will work, anything else will throw `Panic` exception.
   * If `label` is a `PHP` builtin _command/function_ it will execute asynchronously in a **child/subprocess**,
   * by `proc_open`, or `uv_spawn` if **libuv** is loaded.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @see https://www.python.org/dev/peps/pep-0492/#id56
   * @see https://docs.python.org/3.10/reference/expressions.html#await
   *
   * @param string $label `async` function, **reserved** or `PHP` builtin function.
   * @param mixed ...$args
   * @return mixed
   * @throws Panic if the **named** `label` function does not exists.
   */
  function await(string $label, ...$args)
  {
    return Kernel::await($label, ...$args);
  }

  /**
   * This function will `pause` and execute the `label` function, with `arguments`,
   * only functions created with `async`, or some **reserved**,  or
   * a `PHP` builtin callable will work, anything else will throw `Panic` exception.
   */
  \define('await', 'await');

  /**
   * Wrap the **result** with `yield`, or create a `Coroutine::value` object instance of it.
   *
   * Of which will signal and insure the actual return `value/result` is properly picked up.
   * - This should mostly be used within a `async` function if needed.
   * - Mostly when returning `values/results` where `yield` was not used within that code block.
   *
   * use as: `return value($result);`
   *
   * @param mixed $result
   * @param bool $byObject - return a `Coroutine::value` **object**
   * @return mixed
   *
   * @internal
   */
  function value($result, bool $byObject = true)
  {
    if ($byObject) {
      return yield Coroutine::value($result);
    } else {
      yield;
      return yield $result;
    }
  }

  /**
   * Wrap the **result** with `yield`, or create a `Coroutine::value` object instance of it.
   */
  \define('value', 'value');

  /**
   * **Schedule** an `async`, a coroutine _function_ for execution.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://curio.readthedocs.io/en/latest/reference.html#tasks
   * @see https://docs.python.org/3.10/library/asyncio-task.html#creating-tasks
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L331
   *
   * @param Generator|callable|string $awaitableFunction - `async`, a coroutine, or a function to make `awaitable`
   * @param mixed ...$args - if **$awaitableFunction** is `Generator`, $args can hold `customState`, and `customData`
   * - for third party code integration.
   *
   * @return int $task id
   */
  function away($awaitableFunction, ...$args)
  {
    return Kernel::away($awaitableFunction, ...$args);
  }

  /**
   * Create a new task that concurrently executes the `async` function.
   */
  \define('create_task', 'away');

  /**
   * Run awaitable objects in the tasks set concurrently and block until the condition specified by race.
   *
   * Controls how the `gather()` function operates.
   * `gather_wait` will behave like **Promise** functions `All`, `Some`, `Any` in JavaScript.
   *
   * @param array<int|\Generator> $tasks
   * @param int $race - If set, initiate a competitive race between multiple tasks.
   * - When amount of tasks as completed, the `gather` will return with task results.
   * - When `0` (default), will wait for all to complete.
   * @param bool $exception - If `true` (default), the first raised exception is immediately
   *  propagated to the task that awaits on gather().
   * Other awaitables in the aws sequence won't be cancelled and will continue to run.
   * - If `false`, exceptions are treated the same as successful results, and aggregated in the result list.
   * @param bool $clear - If `true`, close/cancel remaining results, `false` (default)
   * @throws \LengthException - If the number of tasks less than the desired $race count.
   *
   * @see https://docs.python.org/3.7/library/asyncio-task.html#waiting-primitives
   *
   * @return array associative `$taskId` => `$result`
   */
  function gather_wait(array $tasks, int $race = 0, bool $exception = true, bool $clear = false)
  {
    return Kernel::gatherWait($tasks, $race, $exception, $clear);
  }

  /**
   * Run awaitable objects in the tasks set concurrently and block until the condition specified by race.
   */
  \define('gather_wait', 'gather_wait');

  /**
   * Run awaitable objects in the taskId sequence concurrently.
   * If any awaitable in taskId is a coroutine, it is automatically scheduled as a Task.
   *
   * If all awaitables are completed successfully, the result is an aggregate list of returned values.
   * The order of result values corresponds to the order of awaitables in taskId.
   *
   * The first raised exception is immediately propagated to the task that awaits on gather().
   * Other awaitables in the sequence won't be cancelled and will continue to run.
   *
   * @see https://docs.python.org/3.7/library/asyncio-task.html#asyncio.gather
   *
   * - This function needs to be prefixed with `yield`
   *
   * @param int|array $taskId
   * @return array[] associative `$taskId` => `$result`
   */
  function gather(...$taskId)
  {
    return Kernel::gather(...$taskId);
  }

  /**
   * Run awaitable objects in the taskId sequence concurrently.
   */
  \define('gather', 'gather');

  /**
   * Wrap the callable with `yield`, this insure the first attempt to execute will behave
   * like a generator function, will switch at least once without actually executing, return object instead.
   * - This function is used by `away()` and others, shouldn't really be called directly.
   *
   * @see https://docs.python.org/3.7/library/asyncio-task.html#awaitables
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L638
   *
   * @param callable $awaitableFunction
   * @param mixed $args
   *
   * @return \Generator
   *
   * @internal
   */
  function awaitable(callable $awaitableFunction = null, ...$args)
  {
    return yield yield $awaitableFunction(...$args);
  }

  /**
   * Similar to `awaitable`, but used mainly to delay scheduling or executing a regular function/method.
   * The executing code will be marked as `stateless`, not storing completion results afterwards.
   * The executing code is not supposed to be made for `yielding`.
   *
   * @param int $delay how many times to pause, _`yield` to event loop_, before executing `$function`
   * @param callable $function
   * @param mixed $args
   *
   * @return mixed
   *
   * @internal
   */
  function delayer(int $delay, callable $function, ...$args)
  {
    if ($delay > 0)
      foreach (\range(1, $delay) as $nan)
        yield;

    $result = $function(...$args);
    yield \stateless_task();

    return $result;
  }

  /**
   * Similar to `awaitable`, but used mainly to delay scheduling or executing a regular function/method.
   */
  \define('delayer', 'delayer');

  /**
   * Block/sleep for delay seconds.
   * Suspends the calling task, allowing other tasks to run.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://docs.python.org/3.9/library/asyncio-task.html#sleeping
   * @source https://github.com/python/cpython/blob/bb0b5c12419b8fa657c96185d62212aea975f500/Lib/asyncio/tasks.py#L593
   *
   * @param float $delay
   * @param mixed $result - If provided, it is returned to the caller when the coroutine complete
   */
  function sleep_for(float $delay = 0.0, $result = null)
  {
    return Kernel::sleepFor($delay, $result);
  }

  /**
   * Suspends the calling task, allowing other tasks to run.
   */
  \define('sleep_for', 'sleep_for');

  /**
   * Suspends the calling task, allowing other tasks to run.
   */
  \define('sleep', 'sleep_for');

  /**
   * Wait for the `callable` to complete with a timeout.
   *
   * @see https://docs.python.org/3.10/library/asyncio-task.html#timeouts
   * @source https://github.com/python/cpython/blob/bb0b5c12419b8fa657c96185d62212aea975f500/Lib/asyncio/tasks.py#L392
   *
   * @param callable $callable
   * @param float $timeout
   * @return mixed
   * @throws TimeoutError If a timeout occurred into `current` task.
   * @throws CancelledError If a timeout occurred into `callable` task.
   */
  function wait_for($callable, float $timeout = 0.0)
  {
    return Kernel::waitFor($callable, $timeout);
  }

  /**
   * Wait for the callable to complete with a timeout.
   */
  \define('wait_for', 'wait_for');

  /**
   * Any blocking operation can be cancelled by a timeout.
   * Throws a `TaskTimeout` exception in the calling task after seconds have elapsed.
   * This function may be used in two ways. You can apply it to the execution of a single coroutine:
   *
   *```php
   *         yield timeout_after(seconds, coro(args))
   *
   * # Or you can use it as an asynchronous context manager to apply a timeout to a block of statements:
   *
   *         async_with(timeout_after(seconds));
   *               // Or
   *         yield with(timeout_after(seconds));
   *            yield coro1(args)
   *            yield coro2(args)
   *            ...
   *```
   * - This function needs to be prefixed with `yield`
   *
   * @param float $timeout
   * @param Generator|callable $callable
   * @param mixed ...$args
   * @return mixed
   * @throws TaskTimeout If a timeout has occurred.
   * @see https://curio.readthedocs.io/en/latest/reference.html#timeout_after
   * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/time.py#L141
   */
  function timeout_after(float $timeout = 0.0, $callable = null, ...$args)
  {
    return Kernel::timeoutAfter($timeout, $callable, ...$args);
  }

  /**
   * Any blocking operation can be cancelled by a timeout.
   */
  \define('timeout_after', 'timeout_after');

  /**
   * **Schedule** an `async`, a coroutine _function_ for execution.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://curio.readthedocs.io/en/latest/reference.html#tasks
   * @see https://docs.python.org/3.10/library/asyncio-task.html#creating-tasks
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L331
   *
   * @param Generator|callable|string $awaitableFunction - `async`, a coroutine, or a function to make `awaitable`
   * @param mixed ...$args - if **$awaitableFunction** is `Generator`, $args can hold `customState`, and `customData`
   * - for third party code integration.
   *
   * @return int $task id
   */
  function spawner($awaitableFunction, ...$args)
  {
    return yield Kernel::away($awaitableFunction, ...$args);
  }

  /**
   * **Schedule** an `async`, a coroutine _function_ for execution.
   */
  \define('spawn', 'spawner');

  /**
   * Wait for a task to terminate.
   * Returns the return value (if any) or throw a `Exception` if the task crashed with an exception.
   * - This function needs to be prefixed with `yield`
   *
   * @param integer $tid task id instance
   * @return mixed
   * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/task.py#L177
   */
  function join_task(int $tid)
  {
    return yield Kernel::joinTask($tid);
  }

  /**
   * 	Wait for the task to terminate and return its result.
   */
  \define('join_task', 'join_task');

  /**
   * 	Wait for the task to terminate and return its result.
   */
  \define('join', 'join_task');

  /**
   * Create an new task.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://docs.python.org/3.9/library/asyncio-task.html#creating-tasks
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L331
   *
   * @return int task ID
   */
  function create_task($awaitableFunction, ...$args)
  {
    return \away($awaitableFunction, ...$args);
  }

  /**
   * Cancel a task by **throwing** a `CancelledError` exception, this will also delay kill/remove
   * the task, the status of such can be checked with `is_cancelled` and `is_cancelling` functions.
   * Optionally pass custom cancel state and error message for third party code integration.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://docs.python.org/3.10/library/asyncio-task.html#asyncio.Task.cancel
   * @source https://github.com/python/cpython/blob/bb0b5c12419b8fa657c96185d62212aea975f500/Lib/asyncio/tasks.py#L181
   *
   * @param int $tid task id instance
   * @param mixed $customState
   * @return bool
   *
   * @throws \InvalidArgumentException
   */
  function cancel_task(int $tid, $customState = null, string $errorMessage = 'Invalid task ID!')
  {
    return Kernel::cancelTask($tid, $customState, $errorMessage);
  }

  /**
   * Cancel a task by **throwing** a `CancelledError` exception, this will also delay kill/remove
   * the task, the status of such can be checked with `is_cancelled` and `is_cancelling` functions.
   */
  \define('cancel_task', 'cancel_task');


  /**
   * Cancel a task by **throwing** a `CancelledError` exception, this will also delay kill/remove
   * the task, the status of such can be checked with `is_cancelled` and `is_cancelling` functions.
   */
  \define('cancel', 'cancel_task');

  /**
   * Cancel _current_ `running` task by **throwing** a `CancelledError` exception, this will also delay kill/remove
   * `current` task, the status of such can be checked with `is_cancelled` and `is_cancelling` functions.
   * Optionally pass custom `cancel` state for third party code integration.
   *
   * - This function needs to be prefixed with `yield`
   * @param mixed $customState
   * @return bool
   */
  function kill_task($customState = null)
  {
    $currentTask = yield Kernel::currentTask();
    return yield Kernel::cancelTask($currentTask, $customState);
  }

  /**
   * Cancel _current_ `running` task by **throwing** a `CancelledError` exception, this will also delay kill/remove
   * `current` task, the status of such can be checked with `is_cancelled` and `is_cancelling` functions.
   */
  \define('kill_task', 'kill_task');

  /**
   * Cancel _current_ `running` task by **throwing** a `CancelledError` exception, this will also delay kill/remove
   * `current` task, the status of such can be checked with `is_cancelled` and `is_cancelling` functions.
   */
  \define('kill', 'kill_task');

  /**
   * Returns the current context task ID
   *
   * - This function needs to be prefixed with `yield`
   *
   * @return int task id instance
   */
  function current_task()
  {
    return Kernel::currentTask();
  }

  /**
   * Returns the current context task ID.
   */
  \define('current_task', 'current_task');

  /**
   * Set current context Task to stateless, meaning not storing any return values or exceptions on completion.
   * The task is not moved to completed task list.
   * This function will return the current context task ID.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @return int
   */
  function stateless_task()
  {
    return \task_type('stateless');
  }

  /**
   * Set current context Task to stateless, meaning not storing any return values or exceptions on completion.
   */
  \define('stateless_task', 'stateless_task');

  /**
   * Set current Task context type, currently either `paralleled`, `async`, `awaited`, `stateless`, or `monitored`.
   * Will return the current task ID.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @param string $context
   * @return int
   */
  function task_type(string $context = 'async')
  {
    return Kernel::taskType($context);
  }

  /**
   * Performs a clean application shutdown, killing tasks/processes, and resetting all data, except **created** `async` functions.
   * - This function needs to be prefixed with `yield`
   *
   * Provide $skipTask incase called by an Signal Handler.
   *
   * @param int $skipTask - Defaults to the main parent task.
   * - The calling `$skipTask` task id will not get cancelled, the script execution will return to.
   * - Use `current_task()` to retrieve caller's task id.
   */
  function shutdown(int $skipTask = 1)
  {
    //if (\isLogger_active()) {
    //  yield \logger_commit();
    //  yield \logger_shutdown();
    //}
    return Kernel::shutdown($skipTask);
  }

  /**
   * Performs a clean application exit and shutdown.
   */
  \define('shutdown', 'shutdown');

  /**
   * Wait on read stream socket to be ready read from,
   * optionally schedule current task to execute immediately/next.
   *
   * - This function needs to be prefixed with `yield`
   */
  function read_wait($stream, bool $immediately = false)
  {
    return Kernel::readWait($stream, $immediately);
  }

  /**
   * Wait on read stream socket to be ready read from.
   */
  \define('read_wait', 'read_wait');

  /**
   * Wait on write stream socket to be ready to be written to,
   * optionally schedule current task to execute immediately/next.
   *
   * - This function needs to be prefixed with `yield`
   */
  function write_wait($stream, bool $immediately = false)
  {
    return Kernel::writeWait($stream, $immediately);
  }

  /**
   * Wait on write stream socket to be ready to be written to.
   */
  \define('write_wait', 'write_wait');

  /**
   * Wait on keyboard input.
   * Will not block other task on `Linux`, will continue other tasks until `enter` key is pressed,
   * Will block on Windows, once an key is typed/pressed, will continue other tasks `ONLY` if no key is pressed.
   * - This function needs to be prefixed with `yield`
   *
   * @return string
   */
  function input_wait(int $size = 256, bool $error = false)
  {
    return Coroutine::input($size, $error);
  }

  /**
   * Wait on keyboard input.
   */
  \define('input_wait', 'input_wait');

  /**
   * Return the `string` of a variable type, or does a check, compared with string of the `type`.
   * Types are: `callable`, `string`, `int`, `float`, `null`, `bool`, `array`, `scalar`,
   * `object`, or `resource`
   *
   * @param mixed $variable
   * @param string|null $type
   * @return string|bool
   */
  function is_type($variable, string $type = null)
  {
    $checks = [
      'is_callable' => 'callable',
      'is_string' => 'string',
      'is_integer' => 'int',
      'is_float' => 'float',
      'is_null' => 'null',
      'is_bool' => 'bool',
      'is_scalar' => 'scalar',
      'is_array' => 'array',
      'is_object' => 'object',
      'is_resource' => 'resource',
    ];

    foreach ($checks as $func => $val) {
      if ($func($variable)) {
        return (empty($type)) ? $val : ($type == $val);
      }
    }

    // @codeCoverageIgnoreStart
    return 'unknown';
    // @codeCoverageIgnoreEnd
  }

  /**
   * Returns current `Coroutine` Loop **instance**.
   *
   * @return CoroutineInterface|null
   */
  function coroutine(): ?CoroutineInterface
  {
    return Co::getLoop();
  }

  /**
   * Reset all `Coroutine` **global/static** `Co` variable data, including `async` functions defined.
   * Can also setup a task's unique `starting` id. This is mainly used for testing only.
   *
   * Just calling this function will also setup *order mode* for `Set` class, to **ordered** for testing purposes.
   *
   * @param boolean $unique
   * @param integer $starting - Set a fixed starting number, otherwise creates a cryptographically secure integer
   * @return void
   *
   * @codeCoverageIgnore
   */
  function coroutine_clear(bool $unique = true, int $starting = 0): void
  {
    $coroutine = \coroutine();
    if ($coroutine instanceof CoroutineInterface) {
      $coroutine->setup(false);
    }

    Co::reset();
    Co::resetAsync();
    Co::setUnique('dirty', 1);
    Co::setMode(true);
    Co::setUnique('max', ($unique ? \random_int(10000, 9999999999) : $starting));
  }

  /**
   * Creates a new task (using the next free task id), wraps **Generator**, a `coroutine` into a `Task` and schedule its execution.
   *
   * @see https://docs.python.org/3.10/library/asyncio-task.html#creating-tasks
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L331
   *
   * @param \Generator $routine
   * @param bool $isAsync should task type be set to a `async` function
   *
   * @return CoroutineInterface
   */
  function coroutine_create(\Generator $routine = null, bool $isAsync = false): CoroutineInterface
  {
    $coroutine = \coroutine();
    if (!$coroutine instanceof CoroutineInterface)
      $coroutine = new Coroutine();

    if (!empty($routine))
      $coroutine->createTask($routine, $isAsync);

    return $coroutine;
  }

  /**
   * This function runs the passed coroutine, taking care of managing the scheduler and
   * finalizing asynchronous generators. It should be used as a main entry point for programs, and
   * should ideally only be called once.
   *
   * @see https://docs.python.org/3.10/library/asyncio-task.html#asyncio.run
   * @see https://curio.readthedocs.io/en/latest/reference.html#basic-execution
   * @source https://github.com/python/cpython/blob/3.10/Lib/asyncio/runners.py
   *
   * @param generator|callable|string $routine - the **main** `coroutine` or `async` function.
   * @param mixed ...$args if **routine** is `async` function.
   * @throws Panic If **routine** not valid.
   */
  function coroutine_run($routine = null, ...$args): void
  {
    $isAsync = false;
    if (\is_string($routine) && Co::isFunction($routine)) {
      $isAsync = true;
      $routine = Co::getFunction($routine)(...$args);
    } elseif (\is_callable($routine)) {
      $routine = \awaitable($routine, ...$args);
    }

    if ($routine instanceof \Generator || empty($routine))
      \coroutine_create($routine, $isAsync)->run();
    else
      \panic("Invalid `coroutine` or no `async` function found!");
  }

  /**
   * Creates an communications Channel between coroutines.
   * Similar to Google Go language - basic, still needs additional functions
   * - This function needs to be prefixed with `yield`
   *
   * @return Channel $channel
   */
  function make()
  {
    return Kernel::make();
  }

  /**
   * Send message to an Channel
   * - This function needs to be prefixed with `yield`
   *
   * @param Channel $channel
   * @param mixed $message
   * @param int $taskId override send to different task, not set by `receiver()`
   */
  function sender(Channel $channel, $message = null, int $taskId = 0)
  {
    $noResult = yield Kernel::sender($channel, $message, $taskId);
    yield;
    return $noResult;
  }

  /**
   * Set task as Channel receiver, and wait to receive Channel message
   * - This function needs to be prefixed with `yield`
   *
   * @param Channel $channel
   */
  function receiver(Channel $channel)
  {
    yield Kernel::receiver($channel);
    $message = yield Kernel::receive($channel);
    return $message;
  }

  /**
   * A goroutine is a function that is capable of running concurrently with other functions.
   * To create a goroutine we use the keyword `go` followed by a function invocation
   * - This function needs to be prefixed with `yield`
   *
   * @see https://www.golang-book.com/books/intro/10#section1
   *
   * @param Generator|callable|string $function
   * @param mixed $args - if `generator`, $args can hold `customState`, and `customData`
   *
   * @return int task id
   */
  function go($function, ...$args)
  {
    return Kernel::away($function, ...$args);
  }

  /**
   * Modeled as in `Go` Language. The behavior of defer statements is straightforward and predictable.
   * There are three simple rules:
   * 1. *A deferred function's arguments are evaluated when the defer statement is evaluated.*
   * 2. *Deferred function calls are executed in Last In First Out order after the* surrounding function returns.
   * 3. *Deferred functions can`t modify return values when is type, but can modify content of reference to array or object.*
   *
   * PHP Limitations:
   * - In this *PHP* defer implementation,
   *  you cant modify returned value. You can modify only content of returned reference.
   * - You must always set first parameter in `defer` function,
   *  the parameter MUST HAVE same variable name as other `defer`,
   *  and this variable MUST NOT exist anywhere in local scope.
   * - You can`t pass function declared in local scope by name to *defer*.
   *
   * Modified from https://github.com/tito10047/php-defer
   *
   * @see https://golang.org/doc/effective_go.html#defer
   *
   * @param Defer|null $previous defer
   * @param callable $callback
   * @param mixed ...$args
   *
   * @throws \Exception
   */
  function defer(&$previous, $callback)
  {
    $args = \func_get_args();
    \array_shift($args);
    \array_shift($args);
    Defer::deferring($previous, $callback, $args);
  }

  /**
   * Modeled as in `Go` Language. Regains control of a panicking `task`.
   *
   * Recover is only useful inside `defer()` functions. During normal execution, a call to recover will return nil
   * and have no other effect. If the current `task` is panicking, a call to recover will capture the value given
   * to panic and resume normal execution.
   *
   * @param Defer|null $previous defer
   * @param callable $callback
   * @param mixed ...$args
   */
  function recover(&$previous, $callback)
  {
    $args = \func_get_args();
    \array_shift($args);
    \array_shift($args);
    Defer::recover($previous, $callback, $args);
  }

  /**
   * Modeled as in `Go` Language.
   *
   * An general purpose function for throwing an Coroutine `Exception`,
   * or some abnormal condition needing to keep an `Task` stack trace.
   *
   * @param string|Throwable $message or `new Exception($message)`
   * @param integer $code
   * @param \Throwable|null $previous
   * @throws Exception|Panic
   */
  function panic($message = '', $code = 0, \Throwable $previous = null)
  {
    if ($message instanceof Panicking)
      throw $message;

    throw new Panic($message, $code, $previous);
  }

  /**
   * Re-raises **throws** the `exception` that is currently being _handled_.
   * This function will also run the `shutdown` process, if it _detect's_ any issue getting current `task` or `coroutine` instance.
   * - This function needs to be prefixed with `yield`
   *
   * @throws Exception|Error
   * @see https://docs.python.org/3.10/reference/simple_stmts.html#raise
   * @codeCoverageIgnore
   */
  function raise()
  {
    try {
      $exception = \coroutine()->getTask(yield \current_task())->exception();
    } catch (\Throwable $th) {
      yield shutdown();
    }

    throw $exception;
  }

  /**
   * Re-raises **throws** the `exception` that is currently being _handled_.
   * This function will also run the `shutdown` process, if it _detect's_ any issue getting current `task` or `coroutine` instance.
   */
  \define('raise', 'raise');

  /**
   * This is useful as a `placeholder` when a statement is required syntactically.
   * - When executed, nothing happens.
   *
   * @return void
   * @see https://docs.python.org/3.10/reference/simple_stmts.html#pass
   */
  function pass(): void
  {
  }

  /**
   * This is useful as a `placeholder` when a statement is required syntactically.
   * - When executed, nothing happens.
   */
  \define('pass', 'pass');

  /**
   * An PHP Functional Programming Primitive.
   *
   * Return a curryied version of the given function. You can decide if you also
   * want to curry optional parameters or not.
   *
   * @see https://github.com/lstrojny/functional-php/blob/master/docs/functional-php.md#currying
   *
   * @param callable $function the function to curry
   * @param bool $required curry optional parameters ?
   * @return callable a curryied version of the given function
   */
  function curry(callable $function, $required = true)
  {
    $reflection = new \ReflectionFunction(\Closure::fromCallable($function));
    $count = $required ?
      $reflection->getNumberOfRequiredParameters() : $reflection->getNumberOfParameters();
    return \curry_n($count, $function);
  }

  /**
   * Return a version of the given function where the $count first arguments are curryied.
   *
   * No check is made to verify that the given argument count is either too low or too high.
   * If you give a smaller number you will have an error when calling the given function. If
   * you give a higher number, arguments will simply be ignored.
   *
   * @see https://github.com/lstrojny/functional-php/blob/master/docs/functional-php.md#curry_n
   *
   * @param int $count number of arguments you want to curry
   * @param callable $function the function you want to curry
   * @return callable a curryied version of the given function
   */
  function curry_n($count, callable $function)
  {
    $accumulator = function (array $arguments) use ($count, $function, &$accumulator) {
      return function (...$newArguments) use ($count, $function, $arguments, $accumulator) {
        $arguments = \array_merge($arguments, $newArguments);
        if ($count <= \count($arguments)) {
          return \call_user_func_array($function, $arguments);
        }
        return $accumulator($arguments);
      };
    };
    return $accumulator([]);
  }
}
