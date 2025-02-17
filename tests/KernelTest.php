<?php

namespace Async\Tests;

use function Async\Worker\spawn_task;

use Async\Kernel;
use Async\TaskInterface;
use Async\CoroutineInterface;
use Async\Panicking;
use Async\TimeoutError;
use Async\CancelledError;
use Async\LengthException;
use Async\InvalidStateError;
use Async\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class KernelTest extends TestCase
{
  protected $counterResult = null;

  protected function setUp(): void
  {
    \coroutine_clear(false);
  }

  protected function controller()
  {
    $onAlreadyCompleted = function (TaskInterface $tasks) {
      $tasks->customState('done');
      $tasks->getCustomData();

      return $tasks->result();
    };

    $onNotStarted = function (TaskInterface $tasks, CoroutineInterface $coroutine) {
      $tasks->customState();
      $coroutine->schedule($tasks);
      $coroutine->execute();
    };

    $onCompleted = function (TaskInterface $tasks) {
      $tasks->customState('done');
      $tasks->getCustomData();

      return $tasks->result();
    };

    $onToClear = function (TaskInterface $tasks) {
      $tasks->customState('cleared');
      $tasks->getCustomData();
    };

    $onError = null;
    $onCancel = null;

    Kernel::gatherController(
      '',
      $onAlreadyCompleted,
      $onNotStarted,
      $onCompleted,
      $onError,
      $onCancel,
      $onToClear
    );
  }

  public function factorial($name, $number)
  {
    $f = 1;
    foreach (range(2, $number + 1) as $i) {
      yield \sleep_for(1);
      $f *= $i;
    }

    return $f;
  }

  public function taskGather()
  {
    \async('factorizing', function ($name, $number) {
      $f = 1;
      foreach (range(2, $number + 1) as $i) {
        yield \sleep_for(1);
        $f *= $i;
      }

      return $f;
    });

    $async = yield \away('factorizing', 'C', 4);

    $this->controller();
    $factorials = yield \gather_wait(
      [
        $this->factorial("A", 2),
        $this->factorial("B", 3),
        $async
      ],
      2
    );

    $this->assertNotEmpty($factorials);
    $this->assertCount(2, $factorials);
  }

  public function testGather()
  {
    \coroutine_run($this->taskGather());
  }

  public function taskGatherException()
  {
    $this->expectException(Panicking::class);
    yield \gather('$one,$two, $this->factorial("C", 4)');
    yield \shutdown();
  }

  public function testGatherException()
  {
    \coroutine_run($this->taskGatherException());
  }

  public function taskGatherInvalidStateError()
  {
    $this->expectException(InvalidStateError::class);
    yield \gather(5);
    yield \shutdown();
  }

  public function testGatherInvalidStateError()
  {
    \coroutine_run($this->taskGatherInvalidStateError());
  }

  public function taskGatherCancelled()
  {
    \async('cancelledLabel', function () {
      yield;
      yield;
      throw new CancelledError('closure cancelled!');
    });

    $this->expectException(CancelledError::class);
    $one = yield \away(cancelledLabel);
    yield \gather($one);
    yield \shutdown();
  }

  public function testGatherCancelled()
  {
    \coroutine_run($this->taskGatherCancelled());
  }

  public function taskGatherOption()
  {
    $this->expectException(LengthException::class);
    yield \gather_wait([1], 3);
    yield \shutdown();
  }

  public function testGatherOption()
  {
    \coroutine_run($this->taskGatherOption());
  }

  public function already($value = 0)
  {
    return \value($value, false);
  }

  public function taskGatherAlreadyCompleted()
  {
    $one = yield \away(function () {
      yield;
      return '1';
    });

    \async('already', function ($value = 0) {
      return \value($value);
    });

    $two = yield \away($this->already(2));
    $three =  yield \away(already, 3);
    $result = yield \gather($one, $two, $three);

    $this->assertEquals([3 => '1', 4 => 2, 5 => 3], $result);
    yield \shutdown();
  }

  public function testGatherAlreadyCompleted()
  {
    \coroutine_run($this->taskGatherAlreadyCompleted());
  }

  public function taskGatherDoesNotError()
  {
    $one = yield \away(function () {
      yield;
      yield;
      return '1';
    });

    \async('errorLabel', function () {
      yield;
      yield;
      throw new \Exception('closure error!');
    });

    $three =  yield \away(errorLabel);
    $result = yield \gather_wait([$one, $three], 0, false);
    $this->assertEquals([3 => '1', 4 => (new \Exception('closure error!'))], $result);
    yield \shutdown();
  }

  public function testGatherDoesNotError()
  {
    \coroutine_run($this->taskGatherDoesNotError());
  }

  public function lapse(int $taskId = null)
  {
    yield \cancel_task($taskId);
  }

  public function taskSleepFor()
  {
    \timer_for('true');
    $done = yield \sleep_for(\random_uniform(1, 1), 'done sleeping');
    $t1 = \timer_for('true');
    $this->assertEquals('done sleeping', $done);
    $this->assertGreaterThan(.9, $t1);
    yield \shutdown();
  }

  public function taskWaitFor()
  {
    try {
      // Wait for at most 0.2 second
      yield \wait_for($this->taskSleepFor(), 0.2);
    } catch (\Throwable $e) {
      $this->assertInstanceOf(TimeoutError::class, $e);
      yield Kernel::shutdown();
    }
  }

  public function testSleepFor()
  {
    \coroutine_run($this->taskSleepFor());
  }

  public function testWaitFor()
  {
    \coroutine_run($this->taskWaitFor());
  }

  public function testCancel()
  {
    $this->expectException(InvalidArgumentException::class);
    \coroutine_run($this->lapse(99));
  }

  public function taskSpawnTask()
  {
    $result = yield spawn_task(function () {
      usleep(1000);
      return 'subprocess';
    });

    $this->assertTrue(\is_type($result, 'int'));
    $output = yield \gather($result);
    $this->assertEquals('subprocess', $output[$result]);
    yield \shutdown();
  }

  public function testSpawnTask()
  {
    \coroutine_run($this->taskSpawnTask());
  }

  public function childTask($break = false)
  {
    $counter = 0;
    while (true) {
      $counter++;
      $this->counterResult = $counter;
      if ($break && ($counter == 2)) {
        throw new CancelledError();
      }

      yield;
    }
  }

  public function taskReadWait()
  {
    yield \away($this->childTask());
    $resource = @\fopen(__DIR__ . \DS . 'list.txt', 'rb');
    \stream_set_blocking($resource, false);
    yield \read_wait($resource);
    $contents = \stream_get_contents($resource);
    \fclose($resource);
    $this->assertGreaterThanOrEqual(2, $this->counterResult);
    $this->assertEquals('string', \is_type($contents));
    yield;
    $this->assertGreaterThanOrEqual(3, $this->counterResult);
    yield \shutdown();
  }

  public function testReadWait()
  {
    \coroutine_run($this->taskReadWait());
  }

  public function taskWriteWait()
  {
    yield \away($this->childTask());
    $resource = fopen('php://temp', 'r+');
    \stream_set_blocking($resource, false);
    yield \write_wait($resource);
    \fwrite($resource, 'hello world');
    \rewind($resource);
    yield \read_wait($resource);
    $result = \stream_get_contents($resource);
    \fclose($resource);
    $this->assertEquals('hello world', $result);
    $this->assertGreaterThanOrEqual(3, $this->counterResult);
    $this->assertEquals('string', \is_type($result));
    yield;
    $this->assertGreaterThanOrEqual(4, $this->counterResult);
    yield \shutdown();
  }

  public function testWriteWait()
  {
    \coroutine_run($this->taskWriteWait());
  }

  public function keyboard()
  {
    if (\IS_WINDOWS) {
      $this->expectException(InvalidArgumentException::class);
    }

    return yield \input_wait(256, \IS_WINDOWS);
  }

  public function taskInput()
  {
    try {
      yield \wait_for($this->keyboard(), 0.1);
    } catch (\Throwable $e) {
      $this->assertInstanceOf(TimeoutError::class, $e);
    }

    yield \shutdown();
  }

  public function testInputAndGather()
  {
    \coroutine_run($this->taskInput());
  }
}
