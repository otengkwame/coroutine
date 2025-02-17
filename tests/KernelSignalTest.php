<?php

namespace Async\Tests;

use function Async\Worker\{signal_task, spawn_kill, spawn_signal};

use Async\InvalidStateError;
use PHPUnit\Framework\TestCase;

class KernelSignalTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('uv_loop_new'))
            $this->markTestSkipped('Test skipped "uv_loop_new" missing.');

        \coroutine_clear();
    }

    public function taskSpawnSignalDelay()
    {
        $sigTask = yield signal_task(\SIGKILL, function ($signal) {
            $this->assertEquals(\SIGKILL, $signal);
        });

        $sigId = yield spawn_signal(function () {
            \usleep(56000);
            return 'subprocess';
        }, \SIGKILL, $sigTask);

        $kill = yield \away(function () use ($sigId) {
            yield;
            $bool = yield spawn_kill($sigId);
            return $bool;
        }, true);

        yield \gather_wait([$sigId], 0, false);
    }

    public function testSpawnSignalDelay()
    {
        \coroutine_run($this->taskSpawnSignalDelay());
    }

    public function taskSpawnSignalResult()
    {
        $sigTask = yield signal_task(\SIGKILL, function ($signal) {
            $this->assertEquals(\SIGKILL, $signal);
        });

        $sigId = yield spawn_signal(function () {
            \usleep(5000);
            return 'subprocess';
        }, \SIGKILL, $sigTask);

        $kill = yield \away(function () use ($sigId) {
            yield;
            $bool = yield spawn_kill($sigId);
            return $bool;
        }, true);

        $output = yield \gather_wait([$sigId, $kill], 0, false);
        $this->assertInstanceOf(\Async\CancelledError::class, $output[$sigId]);
        $this->assertEquals(true, $output[$kill]);
        yield \shutdown();
    }

    public function testSpawnSignalResult()
    {
        \coroutine_run($this->taskSpawnSignalResult());
    }

    public function taskSpawnSignal()
    {
        $sigTask = yield signal_task(\SIGKILL, function ($signal) {
            $this->assertEquals(\SIGKILL, $signal);
        });

        $sigId = yield spawn_signal(function () {
            sleep(2);
            return 'subprocess';
        }, \SIGKILL, $sigTask, 1);

        yield \away(function () use ($sigId) {
            return yield spawn_kill($sigId);
        });

        $this->expectException(InvalidStateError::class);
        yield \gather($sigId);
        yield \shutdown();
    }

    public function testSpawnSignal()
    {
        \coroutine_run($this->taskSpawnSignal());
    }
}
