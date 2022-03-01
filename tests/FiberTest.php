<?php

namespace Async\Tests;

use function Async\Fibers\{create_fiber, starting, resuming, suspending, throwing, fiber_return};

use Async\Co;
use Async\Fiber;
use Async\Panicking;
use PHPUnit\Framework\TestCase;

class FiberTest extends TestCase
{
    protected function setUp(): void
    {
        \coroutine_clear();
    }

    public function fiberArgs()
    {
        create_fiber('fi', function (int $x) {
            return ($x + yield suspending($x));
        });

        yield starting('fi', 1);
        yield resuming('fi', 5);
        $this->assertEquals(6, fiber_return('fi'));
    }

    public function testArgs()
    {
        \coroutine_run($this->fiberArgs());
    }

    public function fiberResume()
    {
        create_fiber('fi', function () {
            $value = yield suspending(1);
            $this->assertEquals(2, $value);
        });

        $value = yield starting('fi');
        $this->assertEquals(1, $value);
        yield resuming('fi', $value + 1);
    }

    public function testResume()
    {
        \coroutine_run($this->fiberResume());
    }

    public function fiberCatch()
    {
        create_fiber('fi', function () {
            try {
                yield suspending('test');
            } catch (\Exception $exception) {
                $this->assertEquals('test', $exception->getMessage());
            }
        });

        $value = yield starting('fi');
        $this->assertEquals('test', $value);

        yield throwing('fi', new \Exception('test'));
    }

    public function testCatch()
    {
        \coroutine_run($this->fiberCatch());
    }

    public function fiberGetReturn()
    {
        create_fiber('fi', function () {
            $value = yield suspending(1);
            return $value;
        });

        $value = yield starting('fi');
        $this->assertEquals(1, $value);
        $this->assertNull(yield resuming('fi', $value + 1));
        $this->assertEquals(2, fiber_return('fi'));
    }

    public function testGetReturn()
    {
        \coroutine_run($this->fiberGetReturn());
    }

    public function fiberStatus()
    {
        create_fiber('fi', function () {
            $fiber = Fiber::this();
            $this->assertTrue($fiber->isStarted());
            $this->assertTrue($fiber->isRunning());
            $this->assertFalse($fiber->isSuspended());
            $this->assertFalse($fiber->isTerminated());
            yield suspending();
        });

        $fiber = Co::getFiber('fi');

        $this->assertFalse($fiber->isStarted());
        $this->assertFalse($fiber->isRunning());
        $this->assertFalse($fiber->isSuspended());
        $this->assertFalse($fiber->isTerminated());

        yield starting('fi');

        $this->assertTrue($fiber->isStarted());
        $this->assertFalse($fiber->isRunning());
        $this->assertTrue($fiber->isSuspended());
        $this->assertFalse($fiber->isTerminated());

        yield resuming('fi');

        $this->assertTrue($fiber->isStarted());
        $this->assertFalse($fiber->isRunning());
        $this->assertFalse($fiber->isSuspended());
        $this->assertTrue($fiber->isTerminated());
    }

    public function testStatus()
    {
        /**
         * --EXPECT--
         *bool(false) / before starting
         *bool(false)
         *bool(false)
         *bool(false)
         *bool(true) / inside fiber
         *bool(true)
         *bool(false)
         *bool(false)
         *bool(true) / after suspending
         *bool(false)
         *bool(true)
         *bool(false)
         *bool(true) / after resuming
         *bool(false)
         *bool(false)
         *bool(true)
         */
        \coroutine_run($this->fiberStatus());
    }

    public function taskCreatingFiberPanickingSame()
    {
        $this->expectException(Panicking::class);
        create_fiber('childTask', function ($av = null) {
        });
        create_fiber('childTask', function ($av = null) {
        });

        yield \shutdown();
    }

    public function testCreatingFiberPanickingSame()
    {
        \coroutine_run($this->taskCreatingFiberPanickingSame());
    }
}
