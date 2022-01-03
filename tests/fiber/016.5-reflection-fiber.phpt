--TEST--
ReflectionFiber basic tests
--SKIPIF--
<?php if (!((float) \phpversion() < 8.1)) print "skip"; ?>
--FILE--
<?php

require 'vendor/autoload.php';

use Async\Fiber;
use Async\ReflectionFiber;

\coroutine_clear(false);

function main()
{

$fiber = new Fiber(function () {
    $fiber = Fiber::this();
    var_dump($fiber->isStarted());
    var_dump($fiber->isRunning());
    var_dump($fiber->isSuspended());
    var_dump($fiber->isTerminated());
    yield Fiber::suspend();
});

$reflection = new ReflectionFiber($fiber);

var_dump($fiber === $reflection->getFiber());

var_dump($reflection->isStarted());
var_dump($reflection->isRunning());
var_dump($reflection->isSuspended());
var_dump($reflection->isTerminated());

yield $fiber->start();

var_dump($reflection->isStarted());
var_dump($reflection->isRunning());
var_dump($reflection->isSuspended());
var_dump($reflection->isTerminated());

var_dump($reflection->getExecutingFile());
var_dump($reflection->getExecutingLine());
var_dump($reflection->getTrace());

yield $fiber->resume();

var_dump($fiber->isStarted());
var_dump($fiber->isRunning());
var_dump($fiber->isSuspended());
var_dump($fiber->isTerminated());

}

\coroutine_run(main());

--EXPECTF--
bool(true)
bool(false)
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
string(%d) %S
int(%d)
array(13) {
%S
%S  object(Async\Task)#%d (%d) {
%S    ["taskId":protected]=>
%S    int(1)
%S    ["daemon":protected]=>
%S    NULL
%S    ["cycles":protected]=>
%S    int(2)
%S    ["coroutine":protected]=>
%S    object(Generator)#%d (0) {
%S    }
%S    ["state":protected]=>
%S    string(7) "running"
%S    ["result":protected]=>
%S    NULL
%S    ["sendValue":protected]=>
%S    NULL
%S    ["caller":protected]=>
%S    NULL
%S    ["beforeFirstYield":protected]=>
%S    bool(false)
%S    ["error":protected]=>
%S    NULL
%S    ["exception":protected]=>
%S    NULL
%S    ["customState":protected]=>
%S    NULL
%S    ["customData":protected]=>
%S    NULL
%S    ["taskType":protected]=>
%S    string(7) "awaited"
%S  }
%S
%S  string(5) "fiber"
%S
%S  int(1)
%S
%S  int(3)
%S
%S  object(Generator)#%d (0) {
%S  }
%S
%S  string(9) "suspended"
%S
%S  NULL
%S
%S  NULL
%S
%S  NULL
%S
%S  bool(true)
%S
%S  NULL
%S
%S  NULL
%S
%S  object(Closure)#%d (0) {
%S  }
}
bool(true)
bool(false)
bool(false)
bool(true)
