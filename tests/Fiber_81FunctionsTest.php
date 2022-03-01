<?php

namespace Async\Tests;

use function Async\Fibers\{create_fiber, starting, resuming, suspending, throwing, fiber_return};

use Async\Co;
use Async\Panicking;
use PHPUnit\Framework\TestCase;

class Fiber_81FunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!((float) \phpversion() >= 8.1))
            $this->markTestSkipped('For PHP 8.1 builtin Fibers.');

        \coroutine_clear();
    }

    public function fiberArgs()
    {
        create_fiber('fi', function (int $x) {
            return ($x + suspending($x));
        });

        starting('fi', 1);
        resuming('fi', 5);
        $this->assertEquals(6, fiber_return('fi'));
    }

    public function testArgs()
    {
        $this->fiberArgs();
    }

    public function fiberResume()
    {
        create_fiber('fi', function () {
            $value = suspending(1);
            $this->assertEquals(2, $value);
        });

        $value = starting('fi');
        $this->assertEquals(1, $value);
        resuming('fi', $value + 1);
    }

    public function testResume()
    {
        $this->fiberResume();
    }

    public function fiberCatch()
    {
        create_fiber('fi', function () {
            try {
                suspending('test');
            } catch (\Exception $exception) {
                $this->assertEquals('test', $exception->getMessage());
            }
        });

        $value = starting('fi');
        $this->assertEquals('test', $value);

        throwing('fi', new \Exception('test'));
    }

    public function testCatch()
    {
        $this->fiberCatch();
    }

    public function fiberGetReturn()
    {
        create_fiber('fi', function () {
            $value = suspending(1);
            return $value;
        });

        $value = starting('fi');
        $this->assertEquals(1, $value);
        $this->assertNull(resuming('fi', $value + 1));
        $this->assertEquals(2, fiber_return('fi'));
    }

    public function testGetReturn()
    {
        $this->fiberGetReturn();
    }

    public function fiberStatus()
    {
        create_fiber('fi', function () {
            $fiber = Co::getFiber('fi');
            $this->assertTrue($fiber->isStarted());
            $this->assertTrue($fiber->isRunning());
            $this->assertFalse($fiber->isSuspended());
            $this->assertFalse($fiber->isTerminated());
            suspending();
        });

        $fiber = Co::getFiber('fi');

        $this->assertFalse($fiber->isStarted());
        $this->assertFalse($fiber->isRunning());
        $this->assertFalse($fiber->isSuspended());
        $this->assertFalse($fiber->isTerminated());

        starting('fi');

        $this->assertTrue($fiber->isStarted());
        $this->assertFalse($fiber->isRunning());
        $this->assertTrue($fiber->isSuspended());
        $this->assertFalse($fiber->isTerminated());

        resuming('fi');

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
        $this->fiberStatus();
    }

    public function taskCreateFiberPanickingSame()
    {
        $this->expectException(Panicking::class);
        create_fiber('childTask', function ($av = null) {
        });
        create_fiber('childTask', function ($av = null) {
        });
    }

    public function testCreateFiberPanickingSame()
    {
        $this->taskCreateFiberPanickingSame();
    }
}
