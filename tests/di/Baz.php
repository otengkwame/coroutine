<?php

namespace Async\Tests\Di;

use Async\Tests\Di\DiInterface;

class Baz
{
    public $foo;
    public function __construct(DiInterface $foo = null)
    {
        $this->foo = $foo;
    }
}
