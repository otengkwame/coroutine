<?php

declare(strict_types=1);

namespace Async;

use Async\Spawn\Channeled;
use Async\Spawn\FutureInterface;
use Async\Channel;
use Async\CoroutineInterface;
use Async\TaskInterface;
use Async\LengthException;
use Async\InvalidStateError;
use Async\InvalidArgumentException;
use Async\TaskTimeout;
use Async\TimeoutError;
use Async\CancelledError;
use Async\Panic;
use Async\FiberInterface;
use Async\Misc\AsyncIterator;
use Async\Misc\Contextify;
use Async\Misc\ContextInterface;
use Async\Misc\InjectionInterface;
use Async\Misc\Semaphore;
use Async\Misc\TimeoutAfter;
use Psr\Container\ContainerInterface;

use function Async\Worker\awaitable_future;

/**
 * The Kernel
 * This class is used for Communication between the tasks and the scheduler
 *
 * The `yield` keyword in your code, act both as an interrupt and as a way to
 * pass information to (and from) the scheduler.
 */
final class Kernel
{
  protected $callback;
  protected static $gatherCount = 0;
  protected static $gatherShouldError = true;
  protected static $gatherShouldClearCancelled = false;

  /**
   * Custom `Gather` not started state.
   * @var string
   */
  protected static $isCustomSate = 'n/a';

  /**
   * Execute on already pre-completed `Gather` tasks.
   * @var callable
   */
  protected static $onPreComplete;

  /**
   * Execute on completed `Gather` tasks.
   * @var callable
   */
  protected static $onCompleted;

  /**
   * Execute on exception `Gather` tasks.
   * @var callable
   */
  protected static $onError;

  /**
   * Execute on cancelled `Gather` tasks.
   * @var callable
   */
  protected static $onCancel;

  /**
   * Execute on not started `Gather` tasks.
   * @var callable
   */
  protected static $onProcessing;

  /**
   * Execute cleanup on `GatherWait()` race tasks no longer needed.
   * @var callable
   */
  protected static $onClear;

  public function __construct(callable $callback)
  {
    $this->callback = $callback;
  }

  /**
   * Tells the scheduler to pass the calling `task` or `fiber`, and itself into the function.
   *
   * @param TaskInterface|FiberInterface $taskFiber
   * @param CoroutineInterface $coroutine
   * @return mixed
   */
  public function __invoke($taskFiber, CoroutineInterface $coroutine)
  {
    if ($taskFiber instanceof TaskInterface || $taskFiber instanceof FiberInterface) {
      $callback = $this->callback;
      return $callback($taskFiber, $coroutine);
    }

    // @codeCoverageIgnoreStart
    \panic('Must be instance of "Async\TaskInterface" or "Async\FiberInterface"');
    // @codeCoverageIgnoreEnd
  }

  /**
   * Returns the current context task ID
   *
   * @return int task id instance
   */
  public static function currentTask()
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) {
        $task->sendValue($task->taskId());
        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Set current Task context type, currently either `paralleled`, `async`, `awaited`, `stateless`, or `monitored`.
   * Will return the current task ID.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @param string $context
   * @return int
   */
  public static function taskType(string $context = 'async')
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($context) {
        $task->taskType($context);
        $task->sendValue($task->taskId());
        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Creates a new task (using the next free task id), wraps **Generator**, a `coroutine` into a `Task` and schedule its execution.
   * Returns the `Task` object/id.
   *
   * @see https://docs.python.org/3.10/library/asyncio-task.html#creating-tasks
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L331
   *
   * @param \Generator $coroutines
   * @param bool $isAsync should task type be set to a `async` function
   *
   * @return int task ID
   */
  public static function createTask(\Generator $coroutines, bool $isAsync = false)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($coroutines, $isAsync) {
        $task->sendValue($coroutine->createTask($coroutines, $isAsync));
        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Creates an Channel similar to Google's Go language
   *
   * @return object
   */
  public static function make()
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) {
        $task->sendValue(Channel::make($task, $coroutine));
        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Set Channel by caller's task, similar to Google Go language
   *
   * @param Channel $channel
   */
  public static function receiver(Channel $channel)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($channel) {
        $channel->receiver($task);
        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Wait to receive message, similar to Google Go language
   */
  public static function receive(Channel $channel)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($channel) {
        $channel->receive();
      }
    );
  }

  /**
   * Send an message to Channel by task id, similar to Google Go language
   *
   * @param mixed $message
   * @param int $taskId
   */
  public static function sender(Channel $channel, $message = null, int $taskId = 0)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($channel, $message, $taskId) {
        $target = $channel->receiverTask();
        $sender = $channel->senderTask();
        $targetTask = $target instanceof TaskInterface
          ? $target
          : $sender;

        $checkTask = $coroutine->getTask($taskId);
        if ($checkTask instanceof TaskInterface && $taskId > 0) {
          $targetTask = $checkTask;
        }

        $targetTask->sendValue($message);
        $coroutine->schedule($targetTask);
        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Cancel a task by **throwing** a `CancelledError` exception, this will also delay kill/remove
   * the task, the status of such can be checked with `is_cancelled` and `is_cancelling` functions.
   * Optionally pass custom cancel state and error message for third party code integration.
   *
   * @see https://docs.python.org/3.10/library/asyncio-task.html#asyncio.Task.cancel
   * @source https://github.com/python/cpython/blob/bb0b5c12419b8fa657c96185d62212aea975f500/Lib/asyncio/tasks.py#L181
   *
   * @param int $tid task id instance
   * @param mixed $customState
   * @param string $errorMessage
   * @return bool
   *
   * @throws \InvalidArgumentException
   */
  public static function cancelTask($tid = 0, $customState = null, string $errorMessage = 'Invalid task ID!', bool $type = false)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($tid, $customState, $errorMessage, $type) {
        $cancelTask = $coroutine->getTask($tid);
        if (!\is_null($cancelTask)) {
          $coroutine->clearTimeout($cancelTask);
          if (!empty($customState))
            $cancelTask->customState($customState);

          $isContext = $task->hasWith() || $cancelTask->hasGroup();
          if ($isContext)
            $coroutine->createTask(\delayer(0, [$coroutine, 'cancelTask'], $tid, $customState, $errorMessage));

          if ($cancelTask->hasCaller()) {
            $unjoined = $cancelTask->getCaller();
            $cancelTask->setCaller();
            $cid = $cancelTask->taskId();
            $unjoined->setException(new CancelledError("Task {$cid}!"));
            $coroutine->schedule($unjoined);
          }

          $error = ($isContext || $type) ? new TaskCancelled("Task {$tid}!") :  new CancelledError("Task {$tid}!");
          $customData = $cancelTask->getCustomData();
          if ($customData instanceof FutureInterface) {
            $customData->stop();
            $cancelTask->setCaller($task);
            $cancelTask->setException($error);
            $task->sendValue(true);
            return $coroutine->schedule($task);
          }

          $cancelTask->setException($error);
          if ($cancelTask instanceof FiberInterface)
            $coroutine->scheduleFiber($cancelTask);
          else
            $coroutine->schedule($cancelTask);

          if ($task->taskId() === $cancelTask->taskId()) {
            $task->taskType('cancellation');
            $task->setException($error);
          } else {
            $task->sendValue(true);
          }

          $coroutine->schedule($task);
        } elseif ($coroutine->isCompleted($tid)) {
          $task->sendValue(true);
          $coroutine->schedule($task);
        } else {
          throw new InvalidArgumentException($errorMessage . ' ' . $tid);
        }
      }
    );
  }

  /**
   * 	Wait for the task to terminate and return its result.
   * - This function needs to be prefixed with `yield`
   *
   * @param integer $tid task id instance
   * @return mixed
   */
  public static function joinTask(int $tid)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($tid) {
        $join = $coroutine->getTask($tid);
        if (!\is_null($join)) {
          $join->setCaller($task);
          if ($join->hasGroup())
            $join->discardGroup();

          if ($join->exception()) {
            $task->setException($join->exception());
            return $coroutine->schedule($task);
          }

          $coroutine->schedule($join);
          return $join->join();
        } else {
          $coroutine->schedule($task);
        }
      }
    );
  }

  /**
   * Performs a clean application shutdown, killing tasks/processes, and resetting all data, except **created** `async` functions.
   *
   * Provide $skipTask incase called by an Signal Handler.
   *
   * @param int $skipTask - Defaults to the main parent task.
   * - The calling `$skipTask` task id will not get cancelled, the script execution will return to.
   * - Use `currentTask()` to retrieve caller's task id.
   */
  public static function shutdown(int $skipTask = 1)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($skipTask) {
        if ($skipTask === 1)
          $skipTask = Co::getUnique('parent');

        $returnTask = $coroutine->getTask($skipTask);
        $coroutine->shutdown($skipTask);
        if ($returnTask instanceof TaskInterface) {
          $coroutine->schedule($returnTask);
        }
      }
    );
  }

  /**
   * Wait on read stream/socket to be ready read from,
   * optionally schedule current task to execute immediately/next for third party code integration.
   *
   * @param resource $streamSocket
   * @param bool $immediately
   */
  public static function readWait($streamSocket, bool $immediately = false)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($streamSocket, $immediately) {
        $coroutine->addReader($streamSocket, $task);
        if ($immediately) {
          $coroutine->schedule($task);
        }
      }
    );
  }

  /**
   * Wait on write stream/socket to be ready to be written to,
   * optionally schedule current task to execute immediately/next for third party code integration.
   *
   * @param resource $streamSocket
   * @param bool $immediately
   */
  public static function writeWait($streamSocket, bool $immediately = false)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($streamSocket, $immediately) {
        $coroutine->addWriter($streamSocket, $task);
        if ($immediately) {
          $coroutine->schedule($task);
        }
      }
    );
  }

  /**
   * Add and wait for result of an blocking `I/O` `future` that runs in parallel.
   * This function turns the calling function internal state/type used by `gather()`
   * to **process/paralleled** which is handled differently.
   *
   * - This function needs to be prefixed with `yield`
   *
   * @see https://docs.python.org/3.7/library/asyncio-subprocess.html#subprocesses
   * @see https://docs.python.org/3.7/library/asyncio-dev.html#running-blocking-code
   *
   * @param callable|shell $command
   * @param int|float|null $timeout The timeout in seconds or null to disable
   * @param bool $display set to show `future` output
   * @param Channeled|resource|mixed|null $channel IPC/CSP communication to be passed to the underlying `Future` instance.
   * @param int|null $channelTask The task id to use for realtime **future** output interaction.
   * @param int $signal
   * @param int $signalTask The task to call when `future` is terminated with a signal.
   *
   * @return mixed
   */
  public static function addFuture(
    $command,
    $timeout = 0,
    bool $display = false,
    $channel = null,
    $channelTask = null,
    int $signal = \SIGKILL,
    $signalTask = null,
    $taskType = null
  ) {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine)
      use ($command, $timeout, $display, $channel, $channelTask, $signal, $signalTask, $taskType) {
        $task->taskType('paralleled');
        $task->setState('process');
        $task->customState($taskType);
        $future = $coroutine->addFuture($command, $timeout, $display, $channel)
          ->then(function ($result) use ($task, $coroutine) {
            $coroutine->cancelProgress($task);
            $task->setState('completed');
            $task->sendValue($result);
            $coroutine->schedule($task);
          })
          ->catch(function (\Throwable $error) use ($task, $coroutine) {
            $coroutine->cancelProgress($task);
            $task->setState('erred');
            $task->setException(new \RuntimeException($error->getMessage()));
            $coroutine->schedule($task);
          })
          ->timeout(function () use ($task, $coroutine, $timeout) {
            $coroutine->cancelProgress($task);
            $task->setState('cancelled');
            $task->setException(new TimeoutError($timeout));
            $coroutine->schedule($task);
          });

        $task->customData($future);

        if ($signal !== 0 && $signalTask === null)
          $signalTask = $task->taskId();

        if ($signal !== 0 && \is_int($signalTask)) {
          $future->signal($signal, function ($signaled)
          use ($task, $coroutine, $signal, $signalTask) {
            $coroutine->cancelProgress($task);
            $task->setState('signaled');
            $signaler = $coroutine->getTask($signalTask);
            if ($signaler instanceof TaskInterface) {
              if ($signaler->hasCaller()) {
                $cancel = $signaler->getCaller();
                $cancel->setCaller();
                if ($cancel->getTimer() || $signaler->getTimer())
                  $cancel->setException(new TaskTimeout(0.0));
                else
                  $cancel->setException(new CancelledError('Task ' . $signalTask . ' signal: ' . $signal));

                return $coroutine->schedule($cancel);
              } else {
                $task->setException(new CancelledError('Task ' . $signalTask . ' signal: ' . $signal));
                $signaler->sendValue($signaled);
                $coroutine->schedule($signaler);
              }
            } else { // @codeCoverageIgnoreStart
              $task->setException(new \Exception(\sprintf('An unhandled signal received: %s', $signal)));
              $coroutine->schedule($task);
            } // @codeCoverageIgnoreEnd

          });
        }

        if ($channel instanceof Channeled && \is_int($channelTask)) {
          $channel->setFuture($future);
          $task->customState([$channel, $channelTask]);
          $future->progress(function ($type, $data)
          use ($coroutine, $channelTask) {
            $ipcTask = $coroutine->getTask($channelTask);
            if ($ipcTask instanceof TaskInterface) {
              $ipcTask->sendValue([$type, $data]);
              $coroutine->schedule($ipcTask);
            }
          });
        }
      }
    );
  }

  /**
   * Add/execute a blocking `I/O` `future` task that runs in parallel.
   * This function will return `int` immediately, use `gather()` to get the result.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://docs.python.org/3.7/library/asyncio-subprocess.html#subprocesses
   * @see https://docs.python.org/3.7/library/asyncio-dev.html#running-blocking-code
   *
   * @param callable|shell $command
   * @param int|float|null $timeout The timeout in seconds or null to disable
   * @param bool $display set to show future output
   * @param Channeled|resource|mixed|null $channel IPC/CSP communication to be passed to the underlying `Future` instance.
   * @param int|null $channelTask The task id to use for realtime **future** output interaction.
   * @param int $signal
   * @param int $signalTask The task to call when `future` is terminated with a signal.
   *
   * @return int
   */
  public static function spawnTask(
    $callable,
    $timeout = 0,
    bool $display = false,
    $channel = null,
    $channelTask = null,
    int $signal = 0,
    $signalTask = null,
    $taskType = 'yielded'

  ) {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine)
      use ($callable, $timeout, $display, $channel, $channelTask, $signal, $signalTask, $taskType) {
        $command = \awaitAble(function ()
        use ($callable, $timeout, $display, $channel, $channelTask, $signal, $signalTask, $taskType) {
          $result = yield yield Kernel::addFuture(
            $callable,
            $timeout,
            $display,
            $channel,
            $channelTask,
            $signal,
            $signalTask,
            $taskType
          );

          return $result;
        });

        $task->sendValue($coroutine->createTask($command));
        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Stop/kill a `future` with `signal`, and also `cancel` the task.
   * - This function needs to be prefixed with `yield`
   *
   * @param int $tid The task id of the `future` task.
   * @param int $signal `Termination/kill` signal constant.
   *
   * @return bool
   */
  public static function spawnKill(int $tid, int $signal = \SIGKILL)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($tid, $signal) {
        $spawnedTask = $coroutine->getTask($tid);
        if ($spawnedTask instanceof TaskInterface) {
          $customData = $spawnedTask->getCustomData();
          if ($customData instanceof FutureInterface) {
            $customData->stop($signal);
          }
        }

        $task->sendValue($coroutine->cancelTask($tid));
        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Add a signal handler for the signal, that's continuously monitored.
   * This function will return `int` immediately, use with `spawn_signal()`.
   * - The `$handler` function will be executed, if `future` is terminated with the `signal`.
   * - Expect the `$handler` to receive `(int $signal)`.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://docs.python.org/3/library/signal.html#signal.signal
   *
   * @param int $signal
   * @param callable $handler
   *
   * @return int
   */
  public static function signalTask(int $signal, callable $handler)
  {
    return Kernel::away(function () use ($signal, $handler) {
      yield;
      while (true) {
        $trapSignal = yield;
        if ($signal === $trapSignal) {
          return $handler($signal);
        }
      }
    });
  }

  /**
   * Add a file change event handler for the path being watched, that's continuously monitored.
   * This function will return `int` immediately, use with `monitor()`, `monitor_file()`, `monitor_dir()`.
   * - The `$handler` function will be executed every time theres activity with the path being watched.
   * - Expect the `$handler` to receive `(?string $filename, int $events, int $status)`.
   * - This function needs to be prefixed with `yield`
   *
   * @param callable $handler
   *
   * @return int
   */
  public static function monitorTask(callable $handler)
  {
    return Kernel::away(function () use ($handler) {
      yield;
      while (true) {
        $fileChanged = yield;
        if (\is_array($fileChanged) && (\count($fileChanged) == 3)) {
          [$name, $event, $status] = $fileChanged;
          $fileChanged = null;
          yield $handler($name, $event, $status);
        }
      }
    });
  }

  /**
   * Add a progress handler for the `future`, that's continuously monitored.
   * This function will return `int` immediately, use with `spawn_progress()`.
   * - The `$handler` function will be executed every time the `future` produces output.
   * - Expect the `$handler` to receive `(string $type, $data)`, where `$type` is either `out` or `err`.
   * - This function needs to be prefixed with `yield`
   *
   * @param callable $handler
   *
   * @return int
   */
  public static function progressTask(callable $handler)
  {
    return Kernel::away(function () use ($handler) {
      yield;
      while (true) {
        $received = yield;
        if (\is_array($received) && (\count($received) == 2)) {
          [$type, $data] = $received;
          $received = null;
          if (!\is_null($data))
            yield $handler($type, $data);
        }
      }
    });
  }

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
  public static function gatherWait(array $tasks, int $race = 0, bool $exception = true, bool $clear = false)
  {
    self::$gatherCount = $race;
    self::$gatherShouldError = $exception;
    self::$gatherShouldClearCancelled = $clear;
    return Kernel::gather(...$tasks);
  }

  /**
   * Allow passing custom functions to control how `gather()` react after task process state changes.
   * This is mainly used for third party integration without repeating `Gather`main functionality.
   *
   * @param string $isCustomSate - for custom status state to check on not stated tasks
   * @param null|callable $onPreComplete - for already finish tasks
   * @param null|callable $onProcessing - for not running tasks
   * @param null|callable $onCompleted - for finished tasks
   * @param null|callable $onError - for erring or failing tasks
   * @param null|callable $onCancel - for aborted cancelled tasks
   * @param null|callable $onClear - for cleanup on tasks not to be used any longer
   */
  public static function gatherController(
    string $isCustomSate = 'n/a',
    ?callable $onPreComplete = null,
    ?callable $onProcessing = null,
    ?callable $onCompleted = null,
    ?callable $onError = null,
    ?callable $onCancel = null,
    ?callable $onClear = null
  ): void {
    self::$isCustomSate = $isCustomSate;
    self::$onPreComplete = $onPreComplete;
    self::$onProcessing = $onProcessing;
    self::$onCompleted = $onCompleted;
    self::$onError = $onError;
    self::$onCancel = $onCancel;
    self::$onClear = $onClear;
  }

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
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L678
   *
   * @param int|array $taskId
   * @return array[] associative `$taskId` => `$result`
   */
  public static function gather(...$taskId)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($taskId) {
        $gatherCount = self::$gatherCount;
        $gatherShouldError = self::$gatherShouldError;
        $gatherShouldClearCancelled = self::$gatherShouldClearCancelled;
        self::$gatherCount = 0;
        self::$gatherShouldError = true;
        self::$gatherShouldClearCancelled = false;

        $isCustomSate = self::$isCustomSate;
        $onPreComplete = self::$onPreComplete;
        $onProcessing = self::$onProcessing;
        $onCompleted = self::$onCompleted;
        $onError = self::$onError;
        $onCancel = self::$onCancel;
        $onClear = self::$onClear;
        self::gatherController();

        /**
         * @var TaskInterface[];
         */
        $taskIdList = [];
        $isGatherListGenerator = false;
        $gatherIdList = \is_array($taskId[0]) ? $taskId[0] : $taskId;
        foreach ($gatherIdList as $id => $value) {
          if ($value instanceof \Generator) {
            $isGatherListGenerator = true;
            $id = $coroutine->createTask($value);
            $taskIdList[$id] = $id;
          } elseif (\is_int($value)) {
            $taskIdList[$value] = $value;
          } else {
            \panic("Invalid access, only array of integers - `task Id`, or generator objects allowed!");
          }
        }

        if ($isGatherListGenerator) {
          $gatherIdList = \array_keys($taskIdList);
        }

        $results = [];
        $count = \count($taskIdList);
        $gatherSet = ($gatherCount > 0);
        if ($gatherSet) {
          if ($count < $gatherCount) {
            throw new LengthException(\sprintf('The (%d) tasks, not enough to fulfill the `race: (%d)` count!', $count, $gatherCount));
          }
        }

        $taskList = $coroutine->currentList();

        $completeList = $coroutine->completedList();
        $countComplete = \count($completeList);
        $gatherCompleteCount = 0;
        $isResultsException = false;

        foreach ($gatherIdList as $index => $tid) {
          if (isset($taskList[$tid]) || isset($completeList[$tid])) {
            // @codeCoverageIgnoreStart
            if (
              isset($taskList[$tid])
              && $taskList[$tid] instanceof TaskInterface
              && $taskList[$tid]->isStateless()
            ) {
              $count--;
              $results[$tid] = null;
              $gatherCompleteCount++;
              unset($taskList[$tid]);
              unset($taskIdList[$tid]);
              unset($gatherIdList[$index]);
            }
            // @codeCoverageIgnoreEnd

            continue;
          } else {
            $isResultsException = new InvalidStateError('Task ' . $tid . ' does not exists.');
            if ($gatherShouldError) {
              $countComplete = 0;
              break;
            } else {
              $results[$tid] = $isResultsException;
              $isResultsException = false;
              unset($gatherIdList[$index]);
            }
          }
        }

        // Check and handle tasks already completed before entering/executing gather().
        if ($countComplete > 0) {
          foreach ($completeList as $id => $tasks) {
            if (isset($taskIdList[$id])) {
              if (\is_callable($onPreComplete)) {
                $result = $onPreComplete($tasks);
              } else {
                $result = $tasks->result();
              }

              if ($result instanceof \Throwable) {
                $isResultsException = $result;
              } else {
                $results[$id] = $result;
              }

              $count--;
              $gatherCompleteCount++;
              unset($taskIdList[$id]);

              // Update running task list.
              $coroutine->updateCompleted($id, $completeList);

              // end loop, if gather race count reached
              if ($gatherCompleteCount == $gatherCount)
                break;
            }
          }
        }

        // Check and update base off gather race and completed count.
        if ($gatherSet) {
          $subCount = ($gatherCount - $gatherCompleteCount);
          if ($gatherCompleteCount != $gatherCount) {
            $count = $subCount;
          } elseif ($gatherCompleteCount == $gatherCount) {
            $count = 0;
          }
        }

        // Skip wait, just proceed to propagate/schedule the exception, if set.
        if ($gatherShouldError && ($isResultsException !== false)) {
          $count = 0;
        }

        // Run and wait until race or count is reached.
        while ($count > 0) {
          foreach ($taskIdList as $id) {
            if (isset($taskList[$id])) {
              $tasks = $taskList[$id];
              // Handle if parallel task, check already completed or has not started.
              if ($tasks->isParallel()) {
                $completeList = $coroutine->completedList();
                if (isset($completeList[$id])) {
                  $tasks = $completeList[$id];
                  $tasks->setState('completed');
                  $tasks->taskType('');
                  continue;
                }

                // Handle if future not running, force run.
                if ($tasks->isFuture()) {
                  $type = $tasks->getCustomState();
                  if (\is_string($type) && $type == 'signaling') {
                    $coroutine->execute('signaling');
                  } elseif (\is_string($type) && $type == 'yielded') {
                    $coroutine->execute(true);
                  } else {
                    $coroutine->execute('channeling');
                  }
                }
              }

              // Handle if any other task not running/pending, force run.
              if (
                $tasks->isCustomState($isCustomSate)
                || $tasks->isPending()
                || $tasks->isRescheduled()
              ) {
                if (\is_callable($onProcessing)) {
                  $onProcessing($tasks, $coroutine);
                } else {
                  try {
                    if (($tasks->isPending() || $tasks->isRescheduled()) && $tasks->isCustomState(true)) {
                      $tasks->customState();
                      $coroutine->schedule($tasks);
                      $tasks->run();
                      continue;
                    }

                    if ($tasks->isPending()) {
                      $coroutine->execute();
                    } elseif ($tasks->isRescheduled()) {
                      $coroutine->execute($tasks->getCycles() > 1);
                    }
                  } catch (\Throwable $error) {
                    $tasks->setState(
                      ($error instanceof CancelledError ? 'cancelled' : 'erred')
                    );

                    $tasks->setException($error);
                  }
                }
                // Handle if task finished.
              } elseif ($tasks->isCompleted()) {
                if (\is_callable($onCompleted)) {
                  $result = $onCompleted($tasks);
                } else {
                  $result = $tasks->result();
                }

                $count--;
                unset($taskList[$id]);
                $coroutine->updateCompleted($id);
                $results[$id] = $result;
                // end loop, if set and race count reached
                if ($gatherSet) {
                  $subCount--;
                  if ($subCount == 0)
                    break;
                }
                // Handle if task erred or cancelled.
              } elseif (
                $tasks->isErred()
                || $tasks->isCancelled()
                || $tasks->isSignaled()
              ) {
                if ($tasks->isErred() && \is_callable($onError)) {
                  $isResultsException = $onError($tasks);
                } elseif ($tasks->isCancelled() && \is_callable($onCancel)) {
                  $isResultsException = $onCancel($tasks);
                } else {
                  $isResultsException = $tasks->result();
                }

                $count--;
                unset($taskList[$id]);
                $coroutine->updateCompleted($id, $taskList, $onClear, false, true);
                // Check and propagate/schedule the exception.
                if ($gatherShouldError) {
                  $count = 0;
                  break;
                } else {
                  $results[$id] = $isResultsException;
                  $isResultsException = false;
                }
              }
            }
          }
        }

        // Check for, update and cancel/close any result not part of race gather count.
        if ($gatherSet && (\is_callable($onClear) || $gatherShouldClearCancelled) && ($isResultsException === false)) {
          $resultId = \array_keys($results);
          $abortList = \array_diff($gatherIdList, $resultId);
          $currentList = $coroutine->currentList();
          $finishedList = $coroutine->completedList();
          foreach ($abortList as $id) {
            if (isset($finishedList[$id])) {
              // Update task list removing tasks already completed that will not be used, mark and execute any custom update/cancel routines
              $coroutine->updateCompleted($id, $finishedList, $onClear);
            } elseif (isset($currentList[$id])) {
              // Update task list removing current running tasks not part of race gather count, mark and execute any custom update, then cancel routine
              $coroutine->updateCompleted($id, $currentList, $onClear, true);
            }
          }
        }

        if ($gatherShouldError && ($isResultsException !== false)) {
          $task->setException($isResultsException);
        } else {
          $task->sendValue($results);
        }

        $coroutine->schedule($task);
      }
    );
  }

  /**
   * Block/sleep for delay seconds.
   * Suspends the calling task, allowing other tasks to run.
   *
   * @see https://docs.python.org/3.9/library/asyncio-task.html#sleeping
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L593
   *
   * @param float $delay
   * @param mixed $result - If provided, it is returned to the caller when the coroutine complete
   */
  public static function sleepFor(float $delay = 0.0, $result = null)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($delay, $result) {
        $coroutine->addTimeout(function () use ($task, $coroutine, $result) {
          $task->setTimer();
          if (!empty($result))
            $task->sendValue($result);
          $coroutine->schedule($task);
        }, $delay, $task->taskId());
      }
    );
  }

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
  public static function waitFor($callable, float $timeout = null)
  {
    return new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($callable, $timeout) {
        if ($callable instanceof \Generator) {
          $taskId = $coroutine->createTask($callable);
        } else {
          $taskId = $coroutine->createTask(\awaitAble($callable));
        }

        $coroutine->addTimeout(function () use ($taskId, $timeout, $task, $coroutine) {
          $task->setTimer();
          if (!empty($timeout)) {
            $cancelTask = $coroutine->getTask($taskId);
            if ($cancelTask instanceof TaskInterface)
              $cancelTask->setException(new CancelledError("Task {$taskId}!"));
            $task->setException(new TimeoutError($timeout));
          } elseif ($coroutine->isCompleted($taskId)) {
            $tasks = $coroutine->getCompleted($taskId);
            $result = $tasks->result();
            $coroutine->updateCompleted($taskId);
            $task->sendValue($result);
          }

          $coroutine->schedule($task);
        }, $timeout, $task->taskId());
      }
    );
  }

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
  public static function timeoutAfter(float $timeout = 0.0, $callable = null, ...$args)
  {
    if ($callable)
      return self::__timeoutAfter($timeout, $callable, ...$args);

    return new TimeoutAfter($timeout);
  }

  protected static function __timeoutAfter(float $timeout = 0.0, $callable = null, ...$args)
  {
    return yield yield new Kernel(
      function (TaskInterface $task, CoroutineInterface $coroutine) use ($callable, $timeout, $args) {
        $skip = false;
        if ($callable instanceof \Generator) {
          $taskId = $coroutine->createTask($callable);
        } elseif (\is_callable($callable)) {
          if ($callable === \run_in_process)
            $skip = true;

          $taskId = $coroutine->createTask(\awaitAble($callable, ...$args));
        }

        $callableTask = $coroutine->getTask($taskId);
        $coroutine->addTimeout(function () use ($timeout, $task, $coroutine, $taskId) {
          $task->setTimer();
          $callableTask = $coroutine->getTask($taskId);
          if (!$coroutine->isCompleted($taskId)) {
            $future = $callableTask->getCustomData();
            if ($future instanceof FutureInterface) {
              $future->stop(\SIGKILL);
              $callableTask->setCaller($task);
              $canceled = function () use ($task, $timeout, $coroutine) {
                $task->setException(new TaskTimeout($timeout));
                $coroutine->schedule($task);
              };

              return $coroutine->createTask(\delayer(1, $canceled));
            } else {
              $coroutine->schedule($callableTask);
              $coroutine->createTask(\delayer(2, [$coroutine, 'cancelTask'], $taskId));
            }

            $task->setException(new TaskTimeout($timeout));
            if ($task->hasCaller()) {
              $caller = $task->getCaller();
              $task->setCaller();
              $coroutine->schedule($caller);
            }
          } else {
            $completed = $coroutine->getCompleted($taskId);
            $result = $completed->result();
            $coroutine->updateCompleted($taskId);
            $task->sendValue($result);
          }

          $coroutine->schedule($task);
        }, $timeout, $task->taskId());

        if (!$skip)
          $coroutine->schedule($callableTask);
      }
    );
  }

  /**
   * Begins an asynchronous context manager that is able to suspend execution in its `__enter()` and `__exit()` methods.
   *  It is a **Error** to use `async_with` outside of an `async` function.
   *
   * @see https://book.pythontips.com/en/latest/context_managers.html
   *
   * @param ContextInterface|resource $context
   * @param ContainerInterface|object|ContextInterface|null $object
   * @param array[] $options
   * @return ContextInterface
   * @throws Panic if no context instance, or `__enter()` method does not return `true`.
   */
  public static function asyncWith($context = null, $object = null, array $options = [])
  {
    $di = $options;
    if (\is_object($object) && !$object instanceof ContextInterface) {
      $inject = $di = $object;
    }

    // @codeCoverageIgnoreStart
    if (
      (!empty($options) && isset($inject))
      && ($inject instanceof ContainerInterface)
    ) {
      if ($inject instanceof InjectionInterface) {
        $last = \array_pop($options);
        if (\is_array($options)) {
          foreach ($options as $className => $friendlyName)
            $inject->set($className, $friendlyName);
        }

        if (\is_array($last)) {
          foreach ($last as $identifier => $parameters) {
            $di = $inject->get($identifier, $parameters);
            if (\is_object($di))
              break;
          }
        }
      } else {
        $identifier = \array_unshift($options);
        $di = $inject->get((string) $identifier);
      }
    }

    if (\is_resource($context)) {
      $context = new Contextify($context, $di);
    }

    if ($object instanceof ContextInterface) {
      yield \ending($object);
    }
    // @codeCoverageIgnoreEnd


    if ($context instanceof ContextInterface) {
      if ($context instanceof Semaphore) {
        yield $context->acquire();
      }

      try {
        yield $context();
      } finally {
        if ($context->entered())
          return $context;

        \panic('No valid context manager found!');
      }
    }
  }

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
  public static function with($context = null, \Closure $as = null)
  {
    $task = \coroutine()->getTask(yield \current_task());
    // @codeCoverageIgnoreStart
    if (\is_resource($context)) {
      $context = new Contextify($context);
      // @codeCoverageIgnoreEnd
    } elseif ($task->hasWith()) {
      $contextTask = $task->getWith();
      $task->setWith($context);
      yield \ending($contextTask);
    }

    if ($context instanceof ContextInterface) {
      yield $context();

      if (!$context->entered())
        \panic('No valid context manager found!');

      if ($context instanceof Semaphore)
        yield $context->acquire();

      try {
        if ($as) {
          yield $as($context);
          try {
            yield $context();

            if ($context instanceof Semaphore)
              yield $context->release();
          } finally {
            if (!$context->exited())
              $context->__exit(new Panic('Context block failed to exit!'));
          }
        }
      } finally {
        return $context;
      }
    }
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
  public static function asyncFor(AsyncIterator $task, \Closure $as)
  {
    while (true) {
      try {
        $item = yield $task->current();
      } finally {
        if ($item !== null)
          yield $as($item);
      }

      if (!$task->valid())
        break;
    }

    if ($task instanceof ContextInterface)
      yield \ending($task);
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
  public static function async(string $label, callable $function): void
  {
    $closure = function (...$args) use ($function) {
      yield;
      $coroutine = \coroutine();
      try {
        $async = $function(...$args);
        if ($async === null)
          return;

        $result = yield $async;
      } catch (\Throwable $error) {
        yield;
        $task = $coroutine->getTask(yield \current_task());
        $task->setState(
          ($error instanceof CancelledError ? 'cancelled' : 'erred')
        );

        $context = $task->getWith();
        $parent = \get_parent_class($error);
        $isParentError = $parent === false || \strpos($parent, 'Async\\') !== false || $parent === 'Exception';
        if ($task->hasCaller()) {
          $unjoined = $task->getCaller();
          if (!$isParentError)
            $error = new \Error($error->getMessage(), $error->getCode(), $error->getPrevious());

          $unjoined->setException($error);
          $coroutine->schedule($unjoined);
        } elseif (($context instanceof ContextInterface && $context->isWith() && $context->withTask() === $task) || $task->hasGroup()) {
          /** @var TaskGroup|ContextInterface */
          $instance = $task->hasGroup() ? $task->getGroup() : $context;
          try {
            yield $instance->__exit($error);
          } catch (\Throwable $e) {
          }

          if ($task->hasGroup())
            $instance->task_done($task, true, $error);
        }

        if ($task->isSelfCancellation()) {
          $task->setException($error);
          return yield yield $coroutine->schedule($task);
        } else {
          $task->setException($error);
        }

        return $coroutine->schedule($task);
      }

      $task = $coroutine->getTask(yield \current_task());
      if ($task instanceof TaskInterface)
        $task->setResult($result);

      return $result;
    };

    Co::addFunction($label, $closure);
  }

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
   * @param string $label `async` function, **reserved**  or `PHP` builtin function.
   * @param mixed ...$arguments
   * @return mixed
   * @throws Panic if the **named** `label` function does not exists.
   */
  public static function await(string $label, ...$arguments)
  {
    switch ($label) {
      case 'sleep':
      case 'sleep_for':
        return yield Kernel::sleepFor(...$arguments);
      case 'cancel':
        return yield Kernel::cancelTask(...$arguments);
      case 'join':
      case 'join_task':
        $tid = \array_shift($arguments);
        return yield Kernel::joinTask($tid);
      case 'spawn':
      case 'spawner':
        $async = \array_shift($arguments);
        return yield Kernel::away($async, ...$arguments);
      case 'gather':
        return yield Kernel::gather(...$arguments);
    }

    if (Co::isFunction($label)) {
      return yield Co::getFunction($label)(...$arguments);
    }

    $display = \strpos($label, '*', 1) !== false;
    $away = \strpos($label, '*', 0);
    $label = \str_replace(['*', ' '], '', $label);
    if (\is_callable($label)) {
      // @codeCoverageIgnoreStart
      $system = function () use ($label, $arguments) {
        return @$label(...$arguments);
      };
      // @codeCoverageIgnoreEnd

      if ($away === 0)
        return yield Kernel::spawnTask($system, 0, $display);

      return yield awaitable_future(function () use ($system, $display) {
        return Kernel::addFuture($system, 0, $display);
      });
    }

    \panic("No function named: '{$label}' exists!");
  }

  /**
   * **Schedule** an `async`, a coroutine _function_ for execution.
   * - This function needs to be prefixed with `yield`
   *
   * @see https://curio.readthedocs.io/en/latest/reference.html#tasks
   * @see https://docs.python.org/3.10/library/asyncio-task.html#creating-tasks
   * @source https://github.com/python/cpython/blob/11909c12c75a7f377460561abc97707a4006fc07/Lib/asyncio/tasks.py#L331
   *
   * @param generator|callable|string $label - `async`, a coroutine, or a function to make `awaitable`
   * @param mixed ...$args - if **$label** is `Generator`, $args can hold `customState`, and `customData`
   * - for third party code integration.
   *
   * @return int $task id
   */
  public static function away($label, ...$args)
  {
    if (\is_string($label) && Co::isFunction($label)) {
      return Kernel::createTask(Co::getFunction($label)(...$args), true);
    }

    return new Kernel(
      function ($task, CoroutineInterface $coroutine) use ($label, $args) {
        if ($label instanceof \Generator) {
          $tid = $coroutine->createTask($label);
          if (!empty($args)) {
            $createdTask = $coroutine->getTask($tid);
            if (($args[0] === 'true') || ($args[0] === true))
              $createdTask->customState(true);
            else
              $createdTask->customState($args[0]);

            if (isset($args[1])) {
              $createdTask->customData($args[1]);
            }
          }

          $task->sendValue($tid);
        } else {
          $task->sendValue($coroutine->createTask(\awaitAble($label, ...$args)));
        }

        $coroutine->isFiber($task)
          ? $coroutine->scheduleFiber($task)
          : $coroutine->schedule($task);
      }
    );
  }

  public static function suspendFiber($data)
  {
    return new Kernel(
      function (FiberInterface $fiber, CoroutineInterface $coroutine) use ($data) {
        $fiber->setState('suspended');
        $suspendTo = $fiber->getCaller();
        $suspendTo->sendValue($data);
        $coroutine->isFiber($suspendTo)
          ? $coroutine->scheduleFiber($suspendTo)
          : $coroutine->schedule($suspendTo);
      }
    );
  }

  public static function startFiber(FiberInterface $fiber)
  {
    return new Kernel(
      function ($caller, CoroutineInterface $coroutine) use ($fiber) {
        $fiber->setCaller($caller);
        $coroutine->scheduleFiber($fiber);
      }
    );
  }

  public static function resumeFiber(FiberInterface $fiber, $data)
  {
    return new Kernel(
      function ($caller, CoroutineInterface $coroutine) use ($fiber, $data) {
        $fiber->setState('rescheduled');
        $fiber->setCaller($caller);
        $fiber->sendValue($data);
        $coroutine->scheduleFiber($fiber);
      }
    );
  }

  public static function throwFiber(FiberInterface $fiber, $exception)
  {
    return new Kernel(
      function ($caller, CoroutineInterface $coroutine) use ($fiber, $exception) {
        $fiber->setState('erred');
        $fiber->setCaller($caller);
        $fiber->setException($exception);
        $coroutine->scheduleFiber($fiber);
      }
    );
  }

  /**
   * Returns the _result_ of a completed `task`.
   *
   * @param integer $tid task id instance
   * @return mixed
   * @throws Exception|Error if _task_ `erred`.
   * @throws InvalidStateError if still `running`, not terminated.
   */
  public static function resultFor(int $tid)
  {
    $result = null;
    $coroutine = \coroutine();
    if ($coroutine->isGroup($tid)) {
      $result = $coroutine->getGroupResult($tid);
    } elseif ($coroutine->isCompleted($tid)) {
      $result = $coroutine->getCompleted($tid)->result();
      $coroutine->updateCompleted($tid);
    } elseif ($coroutine->getTask($tid)) {
      throw new InvalidStateError("{$tid}");
    }

    if ($result instanceof \Throwable)
      throw $result;

    return $result;
  }

  /**
   * Returns the _exception_ of a `task`.
   *
   * @param integer $tid task id instance
   * @return null|Throwable
   * @throws InvalidStateError if _task_ still `running`, not terminated.
   */
  public static function exceptionFor(int $tid): ?\Throwable
  {
    $exception = null;
    $coroutine = \coroutine();
    if ($coroutine->isGroup($tid)) {
      $exception = $coroutine->getGroupResult($tid);
    } elseif ($coroutine->isCompleted($tid)) {
      $exception = $coroutine->getCompleted($tid)->exception();
      $coroutine->updateCompleted($tid);
    } elseif ($coroutine->getTask($tid)) {
      throw new InvalidStateError("{$tid}");
    }

    return ($exception instanceof \Throwable) ? $exception : null;
  }
}
