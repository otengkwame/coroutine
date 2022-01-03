<?php

/**
 * From Curio: A Tutorial Introduction
 * @see https://curio.readthedocs.io/en/latest/tutorial.html#tasks-and-concurrency
 */

include 'vendor/autoload.php';

use Async\Exceptions\TimeoutError;
use Async\Exceptions\CancelledError;

async('countdown', function ($n) {
  while ($n > 0) {
    print('T-minus ' . $n . EOL);
    yield await('sleep', 1);
    $n--;
  }
});

async('kid', function ($x, $y) {
  try {
    yield print('Getting around to doing my homework' . EOL);
    yield await(sleep, 1000);
    return $x * $y;
  } catch (CancelledError $e) {
    print("No go diggy die!" . EOL);
    //throw $e;
    yield shutdown();
  }
});

async('parent', function () {
  $kid_task = yield spawner(kid, 37, 42);
  $count_task = yield spawner(countdown, 10);

  yield join_task($count_task);

  print("Are you done yet?" . EOL);
  try {
    $result = yield timeout_after(10, join_task, $kid_task);
    print("Result: " . $result);
  } catch (TimeoutError $e) {
    print("We've got to go!" . EOL);
    yield cancel_task($kid_task);
  }
});

coroutine_run(parent);
