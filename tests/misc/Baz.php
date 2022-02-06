<?php

namespace Async\Tests;

use Async\Tests\Misc\DiInterface;

class Baz
{
    public $foo;
    public function __construct(DiInterface $foo = null)
    {
        $this->foo = $foo;
    }
}
