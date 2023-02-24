<?php

uv_fs_unlink(uv_default_loop(), "./tmp", function ($result) {
    var_dump($result);
});

uv_run();
