<?php

$in  = uv_pipe_init(uv_default_loop(), ('/' == \DIRECTORY_SEPARATOR));

echo "HELLO ";

$stdio = array();
$stdio[] = uv_stdio_new($in, UV::CREATE_PIPE | UV::READABLE_PIPE);

$fp = fopen("php://stdout", "w");
$stdio[] = uv_stdio_new($fp, UV::INHERIT_FD | UV::WRITABLE_PIPE);

$flags = 0;
uv_spawn(
    uv_default_loop(),
    "php",
    array('-r', 'echo "World, Now spawning! " . PHP_EOL;do {echo "*";usleep(1000);$count++;} while ($count < 26);'),
    $stdio,
    __DIR__,
    [],
    function ($process, $stat, $signal) {
        uv_close($process, function () {
        });
    },
    $flags
);

uv_run();
