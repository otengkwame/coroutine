<?php

$in  = uv_pipe_init(uv_default_loop(), ('/' == \DIRECTORY_SEPARATOR));
$out = uv_pipe_init(uv_default_loop(), ('/' == \DIRECTORY_SEPARATOR));

$signal1 = uv_signal_init();

uv_signal_start($signal1, function ($signal) {
    echo PHP_EOL . 'The CTRL+C signal received, click the [X] to close the window.' . PHP_EOL;
    uv_signal_stop($signal);
}, 2);

$signal2 = uv_signal_init();

uv_signal_start($signal2, function ($signal) {
    echo PHP_EOL . 'The SIGHUP signal received, the OS will close this session window!' . PHP_EOL;
}, 1);

echo "Hello, ";

$stdio = array();
$stdio[] = uv_stdio_new($in, UV::CREATE_PIPE | UV::READABLE_PIPE);
$stdio[] = uv_stdio_new($out, UV::CREATE_PIPE | UV::WRITABLE_PIPE);

$flags = 0;
$pid = uv_spawn(
    uv_default_loop(),
    "php",
    array('-r', 'echo "World! " . PHP_EOL;'),
    $stdio,
    __DIR__,
    [],
    function ($process, $stat, $signal) use ($signal2, $signal1) {
        if ($signal == 9) {
            echo "The process was terminated with 'SIGKILL' or '9' signal!" . PHP_EOL;
        }

        uv_close($process);
        uv_close($signal2);
        uv_close($signal1);
    },
    $flags
);

uv_read_start($out, function ($out, $nread, $buffer) use ($pid) {
    echo $buffer;

    uv_close($out, function () use ($pid) {
        uv_process_kill($pid, 9);
    });
});

uv_run();
