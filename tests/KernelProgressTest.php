<?php

namespace Async\Tests;

use function Async\Worker\{progress_task, spawn_progress};

use Async\Spawn\Channeled;
use Async\Spawn\ChanneledInterface;
use PHPUnit\Framework\TestCase;

class KernelProgressTest extends TestCase
{
    protected function setUp(): void
    {
        if (\IS_MACOS)
            $this->markTestSkipped('Test skipped, broken on "MacOS".');

        \coroutine_clear(false);
    }

    public function taskSpawnProgress()
    {
        $channel = new Channeled;
        $realTimeTask = yield progress_task(function ($type, $data) {
            $this->assertNotNull($type);
            $this->assertNotNull($data);
        });

        $realTime = yield spawn_progress(function () {
            echo 'hello ';
            return 'world';
        }, $channel, $realTimeTask);

        $notUsing = yield \gather($realTime);
        yield \shutdown();
    }

    public function testSpawnProgress()
    {
        \coroutine_run($this->taskSpawnProgress());
    }

    public function taskSpawnProgressResult()
    {
        $channel = new Channeled;
        $realTimeTask = yield progress_task(function ($type, $data) use ($channel) {
            $this->assertNotNull($type);
            $this->assertNotNull($data);
        });

        $realTime = yield spawn_progress(function (ChanneledInterface $ipc) {
            $ipc->send('hello ');
            return 'world';
        }, $channel, $realTimeTask);

        $result = yield \gather($realTime);
        $this->assertEquals('world', $result[$realTime]);
        yield \shutdown();
    }

    public function testSpawnProgressResult()
    {
        \coroutine_run($this->taskSpawnProgressResult());
    }
}
