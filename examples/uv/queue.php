<?php
$loop = uv_default_loop();
echo \getmygid();
$test = 'hello';
$queue = uv_queue_work($loop, function () use ($test) {
    //   global $test;
    sleep(1);
    echo "[queue 1] " . $test . ' ';
    echo \getmygid();
}, function () {
    echo "[finished 1]";
});

$queue = uv_queue_work($loop, function () use ($test) {
    //  global $test;
    sleep(0);
    echo "[queue 2] " . $test . ' ';
    echo \getmygid();
}, function () {
    echo "[finished 1]";
});


uv_run();
