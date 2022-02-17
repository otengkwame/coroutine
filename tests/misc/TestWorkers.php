<?php

declare(strict_types=1);

/**
 * Converted Process Worker tests from Curio
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/tests/test_workers.py
 */

namespace Async\Tests;

use Async\CancelledError;
use Async\TaskTimeout;
use Async\Misc\Event;
use Async\Misc\TaskGroup;
use PHPUnit\Framework\TestCase;

/* this function is in tests\misc\functions.php file
function fib($n)
{
  if ($n <= 2)
    return 1;
  else
    return fib($n - 1) + fib($n - 2);
}

// converted from
def fib(n):
    if n <= 2:
        return 1
    else:
        return fib(n - 1) + fib(n - 2)
*/

class TestWorkers extends TestCase
{
  /**
   * @var Event
   */
  protected $evt = null;

  /**
   * @var Event
   */
  protected $evt2 = null;

  protected $results = null;

  protected function setUp(): void
  {
    \coroutine_clear(false);
  }

  public function test_cpu()
  {
    $this->results = [];

    async('spin', function ($n) {
      while ($n > 0) {
        $this->results[] = $n;
        yield sleep_for(0.1);
        $n--;
      }
    });

    async('cpu_bound', function ($n) {
      $r = yield run_in_process('fib', $n);
      $this->results[] = ['fib', $r];
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(new TaskGroup());
      yield $g->spawn(spin, 10);
      yield $g->spawn(cpu_bound, 36);
      yield ending($g);
    });

    coroutine_run(main);

    $this->assertEquals([
      10, 9, 8, 7, 6, 5, 4, 3, 2, 1,
      ['fib', 14930352]
    ], $this->results);
  }

  public function test_bad_cpu()
  {
    async('main', function () {
      //  if (\IS_PHP8)
      //   yield test_raises_async($this, '\TypeError', run_in_process, 'fib', 'bad1');
      // else
      yield test_raises_async($this, '\RuntimeException', run_in_process, 'fib', 'bad1');
    });

    coroutine_run(main);
  }

  public function test_worker_cancel()
  {
    $this->results = [];

    async('spin', function ($n) {
      while ($n > 0) {
        $this->results[] = $n;
        yield sleep_for(0.1);
        $n--;
      }
    });

    async('blocking', function ($n) {
      $task = yield spawner(run_in_process, 'sleep', $n);
      yield sleep_for(0.55);
      yield cancel_task($task);
      try {
        yield join_task($task);
      } catch (CancelledError $th) {
        $this->results[] = 'cancel';
      }
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(new TaskGroup());
      yield $g->spawn(spin, 10);
      yield $g->spawn(blocking, 5);
      yield ending($g);
    });

    coroutine_run(main);

    $this->assertEquals([
      10, 9, 8, 7, 6, 5, 4, 'cancel', 3, 2, 1
    ], $this->results);

    /* Originally
 assert results == [
        10, 9, 8, 7, 6, 5, 'cancel', 4, 3, 2, 1
    ]*/
  }

  public function test_worker_timeout()
  {
    $this->results = [];

    async('spin', function ($n) {
      while ($n > 0) {
        $this->results[] = $n;
        yield sleep_for(0.1);
        $n--;
      }
    });

    async('blocking', function ($n) {
      try {
        $result = yield timeout_after(0.25, run_in_process, 'sleep', $n);
      } catch (TaskTimeout $th) {
        $this->results[] = 'cancel';
      }
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(new TaskGroup());
      yield $g->spawn(spin, 10);
      yield $g->spawn(blocking, 5);
      yield ending($g);
    });

    coroutine_run(main);

    $this->assertEquals([
      10, 9, 8, 7, 'cancel', 6, 5, 4, 3, 2, 1
    ], $this->results);

    /* Originally
 assert results == [
        10, 9, 8, 7, 6, 5, 'cancel', 4, 3, 2, 1
    ]*/
  }
}
