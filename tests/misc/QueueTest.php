<?php

/**
 * The source of this test is from Curio on GitHub
 *
 * @see https://github.com/dabeaz/curio/blob/master/tests/test_queue.py
 */

namespace Async\Tests;

use function Async\Queues\{
  create_queue,
  queue_get,
  queue_put,
  queue_done,
  queue_join,
  queue_clear
};

use Async\Co;
use Async\Misc\Queue;
use Async\CancelledError;
use Async\TaskTimeout;
use Async\TimeoutError;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{

  protected $results = null;

  protected function setUp(): void
  {
    \coroutine_clear(false);
  }

  public function test_queue_simple()
  {
    $this->results = [];

    async('consumer', function ($queue, $label) {
      while (true) {
        $item = yield $queue->get();
        if ($item === 'None')
          break;
        $this->results[] = [$label, $item];
        yield $queue->task_done();
      }

      yield $queue->task_done();
      $this->results[] = $label . ' done';
    });

    async('producer', function () {
      $queue = new Queue();
      $this->results[] = 'producer_start';
      $c1 = yield away('consumer', $queue, 'cons1');
      $c2 = yield await('spawn', 'consumer', $queue, 'cons2');
      yield sleep_for(0.1);

      foreach (range(0, 3) as $n) {
        yield $queue->put($n);
      }

      yield await('sleep', 0.1);
      foreach (range(0, 1) as $n)
        yield $queue->put('None');

      $this->results[] = 'producer_join';
      yield $queue->join();

      $this->results[] = 'producer_done';
      yield join_task($c1);
      yield join_task($c2);
    });

    coroutine_run('producer');

    $this->assertEquals([
      'producer_start',
      ['cons1', 0],
      ['cons2', 1],
      ['cons1', 2],
      ['cons2', 3],
      'producer_join',
      'cons1 done',
      'cons2 done',
      'producer_done'
    ], $this->results);
  }

  public function test_queue_unbounded()
  {
    $this->results = [];

    async('consumer', function (string $queue, $label) {
      while (True) {
        $item = yield queue_get($queue);
        if ($item === 'None')
          break;
        $this->results[] = [$label, $item];
        yield queue_done($queue);
      }

      yield queue_done($queue);
      $this->results[] = $label . ' done';
    });

    async('producer', function () {
      create_queue('queue');
      $this->results[] = 'producer_start';
      $c1 = yield create_task('consumer', 'queue', 'cons1');
      yield sleep_for(0.1);

      foreach (range(0, 3) as $n) {
        yield queue_put('queue', $n);
      }

      yield queue_put('queue', 'None');
      $this->results[] = 'producer_join';
      yield queue_join('queue');
      $this->results[] = 'producer_done';
      yield join_task($c1);
    });

    coroutine_run('producer');

    $this->assertEquals([
      'producer_start',
      'producer_join',
      ['cons1', 0],
      ['cons1', 1],
      ['cons1', 2],
      ['cons1', 3],
      'cons1 done',
      'producer_done'
    ], $this->results);
  }

  public function test_queue_bounded()
  {
    $this->results = [];

    async('consumer', function ($queue, $label) {
      while (True) {
        $item = yield queue_get($queue);
        if ($item === 'None')
          break;
        $this->results[] = [$label, $item];
        yield queue_done($queue);
        yield sleep_for(0.1);
      }

      yield queue_done($queue);
      $this->results[] = $label . ' done';
    });

    async('producer', function () {
      create_queue('queue', 2);
      $this->results[] = 'producer_start';
      yield create_task('consumer', 'queue', 'cons1');
      yield sleep_for(0.1);

      foreach (range(0, 3) as $n) {
        yield queue_put('queue', $n);
        $this->results[] = ['produced', $n];
      }

      yield queue_put('queue', 'None');
      $this->results[] = 'producer_join';

      yield queue_join('queue');
      $this->results[] = 'producer_done';
    });

    coroutine_run('producer');

    if (\IS_PHP8)
      $this->assertEquals([
        'producer_start',
        ['produced', 0],
        ['produced', 1],
        ['cons1', 0],
        ['produced', 2],
        ['cons1', 1],
        ['produced', 3],
        ['cons1', 2],
        'producer_join',
        ['cons1', 3],
        'producer_done',
        'cons1 done',
      ], $this->results);
    else
      $this->assertEquals([
        'producer_start',
        ['produced', 0],
        ['produced', 1],
        ['cons1', 0],
        ['produced', 2],
        ['cons1', 1],
        ['produced', 3],
        ['cons1', 2],
        'producer_join',
        ['cons1', 3],
        'cons1 done',
        'producer_done',
      ], $this->results);
  }

  public function test_queue_get_cancel()
  {
    # Make sure a blocking get can be cancelled
    $this->results = [];

    async('consumer', function () {
      create_queue('queue');

      try {
        $this->results[] = 'consumer waiting';
        $item = yield queue_get('queue');
        $this->results[] = 'not here';
      } catch (CancelledError $e) {
        $this->results[] = 'consumer cancelled';
      }
    });

    async('driver', function () {
      $task = yield create_task('consumer');
      yield sleep_for(0.5);
      yield cancel_task($task);
    });

    coroutine_run('driver');

    $this->assertEquals([
      'consumer waiting',
      'consumer cancelled'
    ], $this->results);
  }

  public function test_queue_put_cancel()
  {
    # Make sure a blocking put() can be cancelled
    $this->results = [];

    async('producer', function () {
      create_queue('queue', 1);
      $this->results[] = 'producer_start';
      yield queue_put('queue', 0);

      try {
        yield queue_put('queue', 1);
        $this->results[] = 'not here';
      } catch (CancelledError $e) {
        $this->results[] = 'producer_cancel';
      }
    });

    async('driver', function () {
      $task = yield create_task('producer');
      yield sleep_for(0.5);
      yield cancel_task($task);
    });

    coroutine_run('driver');

    $this->assertEquals([
      'producer_start',
      'producer_cancel'
    ], $this->results);
  }

  public function test_queue_size()
  {
    async('main', function () {
      create_queue('q');
      $queue = Co::getQueue('q');
      $this->assertInstanceOf(Queue::class, $queue);

      yield queue_put('q', 1);
      $this->assertEquals($queue->size(), 1);

      queue_clear('q');
      $this->assertNull(Co::getQueue('q'));
    });

    coroutine_run('main');
  }

  public function test_queue_get_timeout()
  {
    # Make sure a blocking get respects timeouts
    $this->results = [];

    async('consumer', function () {
      create_queue('queue');

      try {
        $this->results[] = 'consumer waiting';
        $item = yield timeout_after(0.5, queue_get('queue'));
        $this->results[] = 'not here';
      } catch (TaskTimeout $e) {
        $this->results[] = 'consumer timeout';
        yield shutdown();
      }
    });

    coroutine_run('consumer');

    $this->assertEquals([
      'consumer waiting',
      'consumer timeout'
    ], $this->results);
  }

  public function test_queue_put_timeout()
  {
    # Make sure a blocking put() respects timeouts
    $this->results = [];

    async('producer', function () {
      create_queue('queue', 1);
      $this->results[] = 'producer start';
      yield queue_put('queue', 0);

      try {
        yield timeout_after(0.5, queue_put('queue', 1));
        $this->results[] = 'not here';
      } catch (TaskTimeout $e) {
        $this->results[] = 'producer timeout';
        yield shutdown();
      }
    });

    coroutine_run('producer');

    $this->assertEquals([
      'producer start',
      'producer timeout'
    ], $this->results);
  }
  /*

def test_priority_queue(kernel):
    results = []
    priorities = [4, 2, 1, 3]

    async def consumer(queue):
        while True:
            item = await queue.get()
            if item[1] is None:
                break
            results.append(item[1])
            await queue.task_done()
            await sleep(0.2)
        await queue.task_done()

    async def producer():
        queue = PriorityQueue()

        for n in priorities:
            await queue.put((n, n))

        await queue.put((10, None))

        await spawn(consumer(queue))

        await queue.join()

    kernel.run(producer())
    assert results == sorted(priorities)
*/
}
