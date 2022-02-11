<?php

/**
 * Converted Event tests from Curio
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/tests/test_sync.py#L15
 */

namespace Async\Tests;

use Async\CancelledError;
use Async\TimeoutError;
use Async\Misc\Event;
use Async\TaskTimeout;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
  protected $results = null;

  protected function setUp(): void
  {
    \coroutine_clear(false);
  }

  public function test_event_get_wait()
  {
    $this->results = [];
    async('event_setter', function ($evt, $seconds) {
      $this->results[] = 'sleep';
      yield sleep_for($seconds);
      $this->results[] = 'event_set';
      yield $evt->set();
    });

    async('event_waiter', function ($evt) {
      $this->results[] = 'wait_start';
      $this->results[] = $evt->is_set();
      yield $evt->wait();
      $this->results[] = 'wait_done';
      $this->results[] = $evt->is_set();
      $evt->clear();
      $this->results[] = $evt->is_set();
    });

    async('main', function () {
      $evt = new Event();
      $t1 = yield spawner(event_waiter, $evt);
      $t2 = yield spawner(event_setter, $evt, 1);
      yield join_task($t1);
      yield join_task($t2);
    });

    \coroutine_run(main);

    $this->assertEquals([
      'wait_start',
      False,
      'sleep',
      'event_set',
      'wait_done',
      True,
      False
    ], $this->results);
  }

  public function test_event_get_immediate()
  {
    $this->results = [];
    async('event_setter', function ($evt) {
      $this->results[] = 'event_set';
      yield $evt->set();
    });

    async('event_waiter', function ($evt, $seconds) {
      $this->results[] = 'sleep';
      yield sleep_for($seconds);
      $this->results[] = 'wait_start';
      yield $evt->wait();
      $this->results[] = 'wait_done';
    });

    async('main', function () {
      $evt = new Event();
      $t1 = yield spawner(event_waiter, $evt, 1);
      $t2 = yield spawner(event_setter, $evt);
      yield join_task($t1);
      yield join_task($t2);
    });

    \coroutine_run(main);

    $this->assertEquals([
      'sleep',
      'event_set',
      'wait_start',
      'wait_done',
    ], $this->results);
  }

  public function test_event_wait_cancel()
  {
    $this->results = [];
    async('event_waiter', function ($evt) {
      $this->results[] = 'event_wait';
      try {
        yield $evt->wait();
      } catch (CancelledError $e) {
        $this->results[] = 'event_cancel';
      }
    });

    async('event_cancel', function ($seconds) {
      $evt = new Event();
      $task = yield spawner(event_waiter, $evt);
      $this->results[] = 'sleep';
      yield sleep_for($seconds);
      $this->results[] = 'cancel_start';
      yield cancel_task($task);
      $this->results[] = 'cancel_done';
    });

    \coroutine_run(event_cancel, 1);

    $this->assertEquals([
      'sleep',
      'event_wait',
      'cancel_start',
      'event_cancel',
      'cancel_done',
    ], $this->results);
  }

  public function test_event_wait_timeout()
  {
    $this->results = [];
    async('event_waiter', function (Event $evt) {
      $this->results[] = 'event_wait';
      try {
        yield timeout_after(0.5, $evt->wait());
        print 'should never reach';
      } catch (TaskTimeout $e) {
        $this->results[] = 'event_timeout';
      }
    });

    async('event_run', function ($seconds) {
      $evt = new Event();
      $task = yield spawner(event_waiter, $evt);
      $this->results[] = 'sleep';
      yield sleep_for($seconds);
      $this->results[] = 'sleep_done';
      yield join_task($task);
    });

    \coroutine_run(event_run, 1);
    /*
        assert results == [
            'sleep',
            'event_wait',
            'event_timeout',
            'sleep_done',
        ]
*/
    if (\IS_PHP8)
      $this->assertEquals([
        'sleep',
        'event_wait',
        'event_timeout',
        'sleep_done'
      ], $this->results);
    else
      $this->assertEquals([
        'sleep',
        'event_wait',
        'sleep_done',
        'event_timeout'
      ], $this->results);
  }

  public function test_event_wait_notimeout()
  {
    $this->results = [];
    async('event_waiter', function (Event $evt) {
      $this->results[] = 'event_wait';
      try {
        yield timeout_after(0.5, $evt->wait());
        $this->results[] = 'got event';
      } catch (TaskTimeout $e) {
        $this->results[] = 'event_timeout';
      }

      $evt->clear();
      try {
        yield $evt->wait();
        $this->results[] = 'got event';
      } catch (TaskTimeout $e) {
        $this->results[] = 'bad timeout';
      }
    });

    async('event_run', function () {
      $evt = new Event();
      $task = yield spawner(event_waiter, $evt);
      $this->results[] = 'sleep';
      yield sleep_for(0.25);
      $this->results[] = 'event_set';
      yield $evt->set();
      yield sleep_for(1.0);
      $this->results[] = 'event_set';
      yield $evt->set();
      yield join_task($task);
    });

    \coroutine_run(event_run);

    $this->assertEquals([
      'sleep',
      'event_wait',
      'event_set',
      'got event',
      'event_set',
      'got event'
    ], $this->results);
  }
}
