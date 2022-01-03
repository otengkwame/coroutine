<?php

/**
 * From Curio: A Tutorial Introduction
 * @see https://curio.readthedocs.io/en/latest/tutorial.html#tasks-and-concurrency
 */
include 'vendor/autoload.php';

async('countdown', function ($n) {
  while ($n > 0) {
    print('T-minus ' . $n . EOL);
    yield await('sleep', 1);
    $n--;
  }
});

async('kid', function ($x, $y) {
  yield print('Getting around to doing my homework' . EOL);
  yield await('sleep', 1000);
  return $x * $y;
});

async('parent', function () {
  $kid_task = yield spawner('kid', 37, 42);
  $count_task = yield spawner('countdown', 10);

  yield join_task($count_task);

  print("Are you done yet?" . EOL);
  $result = yield await('join', $kid_task);

  print("Result: " . $result);
});

coroutine_run('parent');
