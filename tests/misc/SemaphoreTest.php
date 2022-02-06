<?php

/**
 * Converted Semaphore tests from Curio
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/sync.py#L184
 */

namespace Async\Tests;

use Async\Misc\Semaphore;
use Async\CancelledError;
use Async\TaskTimeout;
use Async\TimeoutError;
use PHPUnit\Framework\TestCase;

class SemaphoreTest extends TestCase
{
  protected $results = null;

  protected function setUp(): void
  {
    \coroutine_clear(false);
  }

  public function test_sema_sequence()
  {
    $this->results = [];

    async('worker', function ($sema, $label) {
      $this->results[] = $label . ' wait';
      $this->results[] = $sema->locked();
      \async_with($sema);
      $this->assertEquals($sema->value(), 0);
      $this->results[] = $label . ' acquire';
      yield sleep_for(0.25);
      \__with($sema);
      $this->results[] = $label . ' release';
    });

    async('main', function () {
      $sema = new Semaphore();
      $t1 = yield spawner(worker, $sema, 'work1');
      $t2 = yield spawner(worker, $sema, 'work2');
      $t3 = yield spawner(worker, $sema, 'work3');
      yield join_task($t1);
      yield join_task($t2);
      yield join_task($t3);
    });

    \coroutine_run(main);

    $this->assertEquals([
      'work1 wait',
      False,
      'work1 acquire',
      'work2 wait',
      True,
      'work2 acquire',
      'work1 release',
      'work3 wait',
      True,
      'work3 acquire',
      'work2 release',
      'work3 release',
    ], $this->results);

    /* Originally
assert results == [
            'work1 wait',
            False,
            'work1 acquire',
            'work2 wait',
            True,
            'work3 wait',
            True,
            'work1 release',
            'work2 acquire',
            'work2 release',
            'work3 acquire',
            'work3 release',
        ]
*/
  }


  public function test_sema_sequence2()
  {
    $this->results = [];
    async('worker', function ($sema, $label, $seconds) {
      $this->results[] = $label . ' wait';
      $this->results[] = $sema->locked();
      \async_with($sema);
      $this->results[] = $label . ' acquire';
      yield sleep_for($seconds);
      yield \__with($sema);
      $this->results[] = $label . ' release';
    });

    async('main', function () {
      $sema = new Semaphore(2);
      $t1 = yield spawner(worker, $sema, 'work1', 0.25);
      $t2 = yield spawner(worker, $sema, 'work2', 0.30);
      $t3 = yield spawner(worker, $sema, 'work3', 0.35);
      yield join_task($t1);
      yield join_task($t2);
      yield join_task($t3);
    });

    \coroutine_run(main);

    $this->assertEquals([
      'work1 wait',            # Both work1 and work2 admitted
      False,
      'work1 acquire',
      'work2 wait',
      False,
      'work2 acquire',
      'work3 wait',
      false,
      'work3 acquire',
      'work1 release',
      'work2 release',
      'work3 release',
    ], $this->results);

    /* Originally
assert results == [
            'work1 wait',            # Both work1 and work2 admitted
            False,
            'work1 acquire',
            'work2 wait',
            False,
            'work2 acquire',
            'work3 wait',
            True,
            'work1 release',
            'work3 acquire',
            'work2 release',
            'work3 release',
        ]
*/
  }

  public function test_sema_acquire_cancel()
  {
    $this->results = [];
    async('worker', function ($lck) {
      $this->results[] = 'lock_wait';
      try {
        async_with($lck);
        yield \__with($lck);
        $this->results[] = 'never here';
      } catch (CancelledError $th) {
        $this->results[] = 'lock_cancel';
      }
    });

    async('worker_cancel', function ($seconds) {
      $lck = new Semaphore();
      \async_with($lck);
      $task = yield spawner(worker, $lck);
      $this->results[] = 'sleep';
      yield sleep_for($seconds);
      $this->results[] = 'cancel_start';
      yield cancel_task($task);
      $this->results[] = 'cancel_done';
      \__with($lck);
    });

    \coroutine_run(worker_cancel, 1);

    $this->assertEquals([
      'sleep',
      'lock_wait',
      'cancel_start',
      'lock_cancel',
      'cancel_done',
    ], $this->results);
  }


  public function test_sema_acquire_timeout()
  {
    $this->results = [];
    async('worker', function ($lck) {
      $this->results[] = 'lock_wait';
      try {
        yield timeout_after(0.5, $lck->acquire());
        $this->results[] = 'never here';
        yield $lck->release();
      } catch (TaskTimeout $th) {
        $this->results[] = 'lock_timeout';
      }
    });

    async('worker_timeout', function ($seconds) {
      $lck = new Semaphore();
      async_with($lck);
      $w = yield spawner(worker, $lck);
      $this->results[] = 'sleep';
      yield sleep_for($seconds);
      $this->results[] = 'sleep_done';
      yield join_task($w);
      \__with($lck);
    });

    \coroutine_run(worker_timeout, 1);

    $this->assertEquals([
      'sleep',
      'lock_wait',
      'sleep_done',
      'lock_timeout',
    ], $this->results);

    /* Originally
assert results == [
            'sleep',
            'lock_wait',
            'lock_timeout',
            'sleep_done',
        ]
*/
  }
}
