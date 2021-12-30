<?php

namespace Async\Tests;

use Async\Exceptions\Panicking;
use PHPUnit\Framework\TestCase;

class AsyncAwaitTest extends TestCase
{
    protected $result = null;

    protected function setUp(): void
    {
        \coroutine_clear(false);
    }

    public function taskAsyncPanickingSame()
    {
        $this->expectException(Panicking::class);
        \async('childTask', function ($av = null) {
        });

        \async('childTask', function ($av = null) {
        });

        yield \shutdown();
    }

    public function testAsyncPanickingSame()
    {
        \coroutine_run($this->taskAsyncPanickingSame());
    }

    public function taskAwaitPanickingMissing()
    {
        $this->expectException(Panicking::class);
        yield \await('childTask', 1);

        yield \shutdown();
    }

    public function testAwaitPanickingMissing()
    {
        \coroutine_run($this->taskAwaitPanickingMissing());
    }

    public function taskAwait()
    {
        $this->result = null;

        \async('already', function ($value) {
            yield;
            return "received: " . $value;
        });

        \async('repeat', function (int $stop) {
            $counter = 0;
            while (true) {
                $counter++;
                if ($counter == $stop) {
                    $result = yield \await('already', $stop);
                    break;
                }
                yield;
            }

            return $result;
        });

        $toCancel = yield \away(function () {
            try {
                $this->result = 0;
                while (true) {
                    $this->result++;
                    yield;
                }
            } catch (\Async\Exceptions\CancelledError $e) {
            }
        });

        $value = yield \await('repeat', 6);
        yield \cancel_task($toCancel);

        $this->assertGreaterThanOrEqual(7, $this->result);
        $this->assertEquals('received: 6', $value);

        yield \shutdown();
    }

    public function testAwait()
    {
        \coroutine_run($this->taskAwait());
    }

    public function testCoroutineRunError()
    {
        $this->expectException(Panicking::class);

        \coroutine_run('null');
    }

    public function testCoroutineRun()
    {
        \async('childTask', function (int $value) {
            yield;
            $this->assertEquals(2, $value);
        });

        \coroutine_run('childTask', 2);
    }

    public function taskAwaitSleep()
    {
        \timer_for('true');
        $done = yield await('sleep', \random_uniform(1, 1), 'done sleeping');
        $t1 = \timer_for('true');
        $this->assertEquals('done sleeping', $done);
        $this->assertGreaterThan(.9, $t1);
        yield \shutdown();
    }

    public function testAwaitSleep()
    {
        \coroutine_run($this->taskAwaitSleep());
    }

    public function taskAwaitFileGetContents()
    {
        $this->result = null;
        yield \create_task(function () {
            $this->result = 0;
            while (true) {
                $this->result++;
                yield;
            }
        });

        $result = yield await('file_get_contents', __DIR__ . \DS . 'list.txt');
        $this->assertStringContainsString('http://google.com/', $result);
        $this->assertGreaterThan(100, $this->result);
        yield \shutdown();
    }

    public function testAwaitFileGetContents()
    {
        \coroutine_run($this->taskAwaitFileGetContents());
    }

    public function taskAwaitFileGetContentsTask()
    {
        $this->result = null;
        yield \away(function () {
            $this->result = 0;
            while (true) {
                $this->result++;
                yield;
            }
        });

        $tid = yield \await('*file_get_contents', __DIR__ . \DS . 'list.txt');
        $this->assertTrue(\is_type($tid, 'int'));
        $result = yield \gather($tid);
        $this->assertStringContainsString('http://google.com/', $result[$tid]);
        $this->assertGreaterThan(100, $this->result);
        yield \shutdown();
    }

    public function testAwaitFileGetContentsTask()
    {
        \coroutine_run($this->taskAwaitFileGetContentsTask());
    }
}
