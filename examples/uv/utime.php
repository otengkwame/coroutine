<?php

uv_fs_utime(uv_default_loop(), __FILE__, time(), time(), function ($status) {
    var_dump($status);
    echo "Finished\n";
});

uv_run();
