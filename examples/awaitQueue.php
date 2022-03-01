<?php

/**
 * The source of this example is from Curio on GitHub
 *
 * @see https://github.com/dabeaz/curio/blob/master/examples/prodcons.py
 */

include 'vendor/autoload.php';

use Async\Misc\Queue;

async('producer', function (Queue $queue) {
  foreach (range(1, 10) as $i) {
    yield $queue->put($i);
  }

  yield $queue->join();
  print('Producer done' . EOL);
});

async('consumer', function (Queue $queue) {
  while (true) {
    $item = yield $queue->get();
    print('Consumer got ' . $item . EOL);
    yield $queue->task_done();
  }
});

async('main', function () {
  $q = new Queue();
  $prod_task = yield create_task(producer, $q); // Or yield await('spawn', producer, $q)
  $cons_task = yield create_task(consumer, $q); // Or yield await('spawn', consumer, $q)
  yield join_task($prod_task);
  yield cancel_task($cons_task);
  yield shutdown();
});

\coroutine_run(main);
