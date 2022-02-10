<?php

declare(strict_types=1);

/**
 * Converted Semaphore tests from Curio
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/sync.py#L184
 */

namespace Async\Tests;

use Async\Co;
use Async\RuntimeError;
use Async\CancelledError;
use Async\TaskCancelled;
use Async\TaskTimeout;
use Async\Misc\Event;
use Async\Misc\TaskGroup;
use PHPUnit\Framework\TestCase;

if (!\function_exists('test_raises')) {
  function test_raises(TestCase $test, string $exception, callable $function, ...$arguments)
  {
    $test->expectException($exception);
    return $function(...$arguments);
  }
}

class TaskGroupTest extends TestCase
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

  public function test_task_group()
  {
    async('child', function ($x, $y) {
      return value($x + $y);
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(\task_group());
      $t1 = yield $g->spawn(child, 1, 1);
      $t2 = yield $g->spawn(child, 2, 2);
      $t3 = yield $g->spawn(child, 3, 3);
      yield __with($g);

      $this->assertEquals(result_for($t1), 2);
      $this->assertEquals(result_for($t2), 4);
      $this->assertEquals(result_for($t3), 6);
      $this->assertEquals([3 => 2, 4 => 4, 5 => 6], group_results($g));
    });

    coroutine_run(main);
  }

  public function test_task_group_existing()
  {
    $this->evt = new Event();
    async('child', function ($x, $y) {
      return value($x + $y);
    });

    async('child2', function ($x, $y) {
      yield $this->evt->wait();
      return $x + $y;
    });

    async('main', function () {
      $t1 = yield spawner(child, 1, 1);
      $t2 = yield spawner(child2, 2, 2);
      $t3 = yield spawner(child2, 3, 3);
      $t4 = yield spawner(child, 4, 4);
      yield join_task($t1);
      yield join_task($t4);

      /** @var TaskGroup */
      $g = yield async_with(task_group([$t1, $t2, $t3]));
      yield $this->evt->set();
      yield $g->add_task($t4);
      yield __with($g);

      $this->assertEquals(result_for($t1), 2);
      $this->assertEquals(result_for($t2), 4);
      $this->assertEquals(result_for($t3), 6);
      $this->assertEquals(result_for($t4), 8);
      $this->assertEquals([3 => 2, 4 => 4, 5 => 6, 6 => 8], group_results($g));
    });

    \coroutine_run(main);
  }

  public function test_task_any_cancel()
  {
    $this->evt = new Event();
    async('child', function ($x, $y) {
      return value($x + $y);
    });

    async('child2', function ($x, $y) {
      yield $this->evt->wait();
      return $x + $y;
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(task_group([], 'any'));
      $t1 = yield $g->spawn(child, 1, 1);
      $t2 = yield $g->spawn(child2, 2, 2);
      $t3 = yield $g->spawn(child2, 3, 3);
      yield __with($g);

      $this->assertEquals(result_for($t1), 2);
      $this->assertEquals($g->completed(), $t1);
      $this->assertTrue(is_cancelled($t2));
      $this->assertTrue(is_cancelled($t3));
    });

    \coroutine_run(main);
  }

  public function test_task_any_error()
  {
    $this->evt = new Event();
    async('child', function ($x, $y) {
      throw new \Exception('error');
      return value($x + $y);
    });

    async('child2', function ($x, $y) {
      yield $this->evt->wait();
      return $x + $y;
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(task_group([], 'any'));
      $t1 = yield $g->spawn(child, 1, '1');
      $t2 = yield $g->spawn(child2, 2, 2);
      $t3 = yield $g->spawn(child2, 3, 3);
      yield __with($g);

      try {
        $result =  $g->result();
        print 'should never show: ' . $result;
      } catch (\Throwable $th) {
        $this->assertInstanceOf(\Exception::class, $th);
      }

      $this->assertInstanceOf(\Exception::class, $g->exception());
      $this->assertInstanceOf(\Exception::class, exception_for($t1));
      $this->assertTrue(is_cancelled($t2));
      $this->assertTrue(is_cancelled($t3));
      $this->assertCount(1, $g->exceptions());
    });

    \coroutine_run(main);
  }

  public function test_task_group_iter()
  {
    $this->results = [];

    async('child', function ($x, $y) {
      return value($x + $y);
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(task_group());
      yield $g->spawn(child, 1, 1);
      yield $g->spawn(child, 2, 2);
      yield $g->spawn(child, 3, 3);
      yield async_for($g, function ($tid) {
        $this->results[] = result_for($tid);
      });

      $this->assertEquals([2, 4, 6], $this->results);
      $this->assertEquals([], $g->results());    # Explicit collection of results prevents collections on the group
    });

    coroutine_run(main);
  }

  public function test_task_wait_none()
  {
    $this->evt = new Event();
    async('child2', function ($x, $y) {
      yield $this->evt->wait();
      return $x + $y;
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(task_group([], 'None'));
      $t2 = yield $g->spawn(child2, 2, 2);
      $t3 = yield $g->spawn(child2, 3, 3);
      yield __with($g);

      $this->assertTrue(is_cancelled($t2));
      $this->assertTrue(is_cancelled($t3));
    });

    coroutine_run(main);
  }

  public function test_task_group_error()
  {
    $this->evt = new Event();
    async('child', function ($x, $y) {
      $result = $x + $y;
      yield $this->evt->wait();
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(task_group());
      $t1 = yield $g->spawn(child, 1, 1);
      $t2 = yield $g->spawn(child, 2, 2);
      $t3 = yield $g->spawn(child, 3, 'bad');
      yield __with($g);

      if (\IS_PHP81)
        $this->assertInstanceOf(\TypeError::class, exception_for($t3));
      else
        $this->assertInstanceOf(\Exception::class, exception_for($t3));

      $this->assertEquals($g->completed(), $t3);
      $this->assertTrue(is_cancelled($t1));
      $this->assertTrue(is_cancelled($t2));
    });

    \coroutine_run(main);
  }

  public function test_task_group_error_block()
  {
    // @todo $this->markTestSkipped('Test skipped, works, but does not work as intended.');

    $this->evt = new Event();
    async('child', function ($x, $y) {
      $result = $x + $y;
      yield $this->evt->wait();
    });

    async('main', function () {
      try {
        /** @var TaskGroup */
        $g = yield async_with(task_group());
        $t1 = yield $g->spawn(child, 1, 1);
        $t2 = yield $g->spawn(child, 2, 2);
        $t3 = yield $g->spawn(child, 3, 3);
        panic(new RuntimeError('help'));
      } catch (RuntimeError $th) {
        $this->assertEquals('help', $th->getMessage());
      }

      // These tests should be true to show the tasks was cancelled, but tasks are still running.
      $this->assertFalse(is_cancelled($t1));
      $this->assertFalse(is_cancelled($t2));
      $this->asserTFalse(is_cancelled($t3));

      // Force stop and exit, otherwise it stalls cause blocking wait Event still waiting to be set/released.
      yield shutdown();
    });

    \coroutine_run(main);
  }

  public function test_task_group_join()
  {
    $this->evt = new Event();
    async('child', function ($x, $y) {
      $result = $x + $y;
      yield $this->evt->wait();
      return $result;
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(task_group());
      $t1 = yield $g->spawn(child, 1, 'foo');
      $t2 = yield $g->spawn(child, 2, 2);
      $t3 = yield $g->spawn(child, 3, 3);
      try {
        yield join_task($t1);
      } catch (\Throwable $e) {
        $this->assertInstanceOf(\Error::class, $e);
        $this->assertInstanceOf(\Error::class, exception_for($t1));
        // test_raises($this, '\Error', result_for, $t1);
      }

      # These assert that the error has not cancelled other tasks
      // $this->expectException(RuntimeError::class);
      // result_for($t2);

      //$this->expectException(RuntimeError::class);
      //exception_for($t2);

      yield $this->evt->set();
      yield __with($g);

      # Assert that other tasks ran to completion
      $this->assertFalse(is_cancelled($t2));
      $this->assertFalse(is_cancelled($t3));
      $this->assertEquals([4 => 4, 5 => 6], $g->results());
    });

    \coroutine_run(main);
  }


  function test_task_group_cancel()
  {
    $this->evt = new Event();
    $this->evt2 = new Event();

    async('child', function () {
      try {
        yield $this->evt->wait();
      } catch (CancelledError $e) {
        $this->assertInstanceOf(CancelledError::class, $e);
        throw $e;
      }
    });

    async('coro', function () {
      try {
        /** @var TaskGroup */
        $g = yield async_with(task_group());
        $t1 = yield $g->spawn(child);
        $t2 = yield $g->spawn(child);
        $t3 = yield $g->spawn(child);
        yield $this->evt2->set();
      } catch (CancelledError $th) {
        $this->assertTrue(is_cancelling($t1));
        $this->assertTrue(is_cancelling($t2));
        $this->assertTrue(is_cancelling($t3));
        throw $th;
      }
    });

    async('main', function () {
      $t = yield spawner(coro);
      yield $this->evt2->wait();
      yield cancel_task($t);
    });

    \coroutine_run(main);
  }

  public function test_task_group_timeout()
  {
    $this->evt = new Event();

    async('child', function () {
      try {
        yield $this->evt->wait();
      } catch (TaskCancelled $e) {
        $this->assertInstanceOf(TaskCancelled::class, $e);
        throw $e;
      }
    });

    async('coro', function () {
      try {
        yield with(timeout_after(0.25));
        try {
          /** @var TaskGroup */
          $g = yield with(task_group());
          $t1 = yield $g->spawn(child);
          $t2 = yield $g->spawn(child);
          $t3 = yield $g->spawn(child);
          yield __with($g);
        } catch (CancelledError $th) {
          $this->assertTrue(is_cancelled($t1));
          $this->assertTrue(is_cancelled($t2));
          $this->assertTrue(is_cancelled($t3));
          throw $th;
        }
      } catch (TaskTimeout $e) {
        $this->assertInstanceOf(TaskTimeout::class, $e);
      }
    });

    \coroutine_run(coro);
  }

  public function test_task_group_cancel_remaining()
  {
    $this->evt = new Event();

    async('child', function ($x, $y) {
      return \value($x + $y);
    });

    async('waiter', function () {
      yield $this->evt->wait();
    });

    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(task_group());
      $t0 = yield $g->spawn(child, 1, 1);
      $t1 = yield $g->spawn(child, 2, 2);
      $t2 = yield $g->spawn(waiter);
      $t3 = yield $g->spawn(waiter);

      $t = yield $g->next_done();
      $this->assertEquals($t, $t0);

      $r = yield $g->next_result();
      $this->assertEquals($r, 4);

      yield $g->cancel_remaining();

      $this->assertTrue(is_cancelled($t2));
      $this->assertTrue(is_cancelled($t3));
    });

    coroutine_run(main);
  }

  public function test_task_group_use_error()
  {
    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(new TaskGroup());
      $t1 = yield $g->spawn(sleep, 0);
      yield test_raises($this, 'Async\RuntimeError', function () use ($g, $t1) {
        yield $g->add_task($t1);
      });
    });

    \coroutine_run(main);
  }

  public function test_task_group_empty()
  {
    async('main', function () {
      /** @var TaskGroup */
      $g = yield async_with(new TaskGroup());
      yield __with($g);

      $this->assertNull($g->exception());
      $this->assertEquals([], $g->exceptions());
      $this->assertEquals([], $g->results());

      test_raises($this, 'Async\RuntimeError', [$g, 'result']);
    });

    coroutine_run(main);
  }

  public function test_self_cancellation()
  {
    async('suicidal_task', function () {
      $tid = yield current_task();
      $this->expectException(CancelledError::class);
      yield cancel_task($tid);
      # Cancellation is delivered the next time we block
      yield \sleep_for(0);
    });

    coroutine_run(suicidal_task);
  }
  /*
def test_task_group_result(kernel):
    async def child(x, y):
        return x + y


    async def main():
        async with TaskGroup(wait=any) as g:
            await g.spawn(child, 1, 1)
            await g.spawn(child, 2, 2)
            await g.spawn(child, 3, 3)


        assert g.result == 2

    kernel.run(main())


def test_late_join(kernel):
    async def child():
        pass


    async def main():
        t = await spawn(child)
        await sleep(0.1)
        await t.cancel()
        assert t.joined
        assert t.terminated
        assert not t.cancelled


    kernel.run(main)


def test_task_group_join_done(kernel):
    async def add(x, y):
        return x + y


    async def task():
        async with TaskGroup(wait=all) as w:
            await w.spawn(add, 1, 1)
            await w.spawn(add, 2, 2)
            t3 = await w.spawn(add, 3, 3)
            r3 = await t3.join()
            assert r3 == 6


        assert w.results == [2, 4]

    kernel.run(task)

*/
}
