<?php

namespace Async\Tests;

use parallel\Runtime;
use PHPUnit\Framework\TestCase;

class RunTimeParallelTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('uv_loop_new'))
            $this->markTestSkipped('Test skipped "uv_loop_new" missing.');

        \coroutine_clear(false);
    }

    public function testShowing_future_as_return_value()
    {
        $runtime = new Runtime;
        $future  = $runtime->run(function () {
            echo "World";
            return 'hello';
        });

        $this->expectOutputString('World');
        $this->assertEquals('hello', $future->value());
    }

    public function testShowing_future_as_synchronization_point()
    {
        $runtime = new Runtime;
        $future  = $runtime->run(function () {
            echo "in child ";
            for ($i = 0; $i < 500; $i++) {
                if ($i % 10 == 0) {
                    echo ".";
                }
            }

            echo " leaving child";
        });

        $future->value();
        echo ' parent continues';
        $this->expectOutputRegex('/[..... leaving child parent continues]/');
    }
}
