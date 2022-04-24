<?php

declare(strict_types=1);

/**
 * Converted Semaphore tests from Curio
 * @source https://github.com/dabeaz/curio/blob/27ccf4d130dd8c048e28bd15a22015bce3f55d53/curio/sync.py#L184
 */

namespace Async\Tests;

use Async\RuntimeError;
use Async\CancelledError;
use Async\TaskCancelled;
use Async\TaskTimeout;
use Async\Misc\Event;
use Async\Misc\TaskGroup;
use PHPUnit\Framework\TestCase;

class TaskGroupMethodTest extends TestCase
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

  public function childMethod($x, $y)
  {
    return value($x + $y, false);
  }

  public function mainMethod()
  {
    yield method_task();
    /** @var TaskGroup */
    $g = yield async_with(\task_group());
    $t1 = yield $g->spawn($this->childMethod(1, 1));
    $t2 = yield $g->spawn($this->childMethod(2, 2));
    $t3 = yield $g->spawn($this->childMethod(3, 3));
    yield ending($g);

    $this->assertEquals(result_for($t1), 2);
    $this->assertEquals(result_for($t2), 4);
    $this->assertEquals(result_for($t3), 6);
    $this->assertEquals([3 => 2, 4 => 4, 5 => 6], group_results($g));
  }

  public function test_task_type_method()
  {
    coroutine_run($this->mainMethod());
  }

  public function child1($x, $y)
  {
    yield method_task();
    throw new \Exception('error');
    return value($x + $y, false);
  }

  public function child2($x, $y)
  {
    yield method_task();
    yield $this->evt->wait();
    return $x + $y;
  }

  public function main_method()
  {
    $this->evt = new Event();

    //  $this->expectException(TaskCancelled::class);
    //  $this->expectErrorMessage('The operation has been cancelled, with: Task 4!');

    yield method_task();
    /** @var TaskGroup */
    $g = yield async_with(task_group([], 'any'));
    $t1 = yield $g->spawn($this->child1(1, '1'));
    $t2 = yield $g->spawn($this->child2(2, 2)); //Task 4
    $t3 = yield $g->spawn($this->child2(3, 3));
    yield ending($g);

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
  }

  public function test_task_type_method_any_error()
  {
    \coroutine_run($this->main_method());
  }
}
