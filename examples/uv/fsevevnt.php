<?php

$fsevent = uv_fs_event_init(uv_default_loop(), "/tmp/", function ($rsc, $name, $event, $stat) {
	var_dump($name, $event);
	print PHP_EOL;
}, 0);

uv_run();
